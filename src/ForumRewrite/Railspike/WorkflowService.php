<?php

declare(strict_types=1);

namespace ForumRewrite\Railspike;

use PDO;
use RuntimeException;
use ForumRewrite\Security\ApprovalPolicy;
use ForumRewrite\Security\OpenPgpSignatureVerifier;

final class WorkflowService
{
    public function __construct(public readonly WorkflowStore $store, private readonly PDO $readModel, private readonly string $repositoryRoot, private readonly mixed $verify = null) {}

    public function authorize(?array $actor): void
    {
        if ($actor === null || ($actor['_authenticated_identity'] ?? false)!==true || (int)($actor['is_approved'] ?? 0)!==1 || !ApprovalPolicy::isOperator($this->repositoryRoot, (string)($actor['identity_id'] ?? ''))) {
            throw new RuntimeException('Approved operator authentication required.');
        }
    }

    public static function eligible(array $post, array $thread): bool
    {
        if (($post['author_label'] ?? '') === 'reply-agent' || !empty($post['retracted_at'])) return false;
        $tags=array_merge(json_decode($post['board_tags_json'] ?? '[]', true) ?: [], json_decode($thread['thread_labels_json'] ?? '[]', true) ?: []);
        return in_array('railspike', array_map('strtolower', $tags), true);
    }

    public function prepare(array $input, array $actor): string
    {
        $this->authorize($actor);
        $action=(string)($input['action'] ?? '');
        if (!in_array($action, ['start','answer','changes','approve','retry'], true)) throw new RuntimeException('Unknown workflow action.');
        return json_encode(['instance'=>$this->store->instance(),'actor'=>$actor['identity_id'], 'action'=>$action,
            'post_id'=>(string)($input['post_id'] ?? ''), 'workflow_id'=>(string)($input['workflow_id'] ?? ''),
            'version'=>(int)($input['version'] ?? 0), 'payload'=>$input['payload'] ?? [],
            'nonce'=>bin2hex(random_bytes(16)), 'expires'=>time()+300], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function apply(string $text, string $signature, array $actor): array
    {
        $this->authorize($actor);
        if (strlen($text)>65536 || strlen($signature)>16384) throw new RuntimeException('Action too large.');
        $a=json_decode($text,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($a) || ($a['instance'] ?? '')!==$this->store->instance() || ($a['actor'] ?? '')!==$actor['identity_id'] || !is_int($a['expires'] ?? null) || $a['expires']<time() || $a['expires']>time()+300) throw new RuntimeException('Invalid or expired action.');
        $ok=$this->verify !== null ? ($this->verify)($actor,$text,$signature) : (new OpenPgpSignatureVerifier())->verifyDetached((string)$actor['public_key'],$text,$signature,(string)$actor['signer_fingerprint'])['ok'];
        if (!$ok) throw new RuntimeException('Invalid action signature.');
        return $this->store->transaction(function () use ($a,$text,$signature,$actor): array {
            $this->authorize($actor);
            $receipt=hash('sha256',$text);
            $id=$this->store->receipt($receipt);
            if ($id !== null) return $this->store->find($id);
            if ($a['action']==='start') {
                $post=$this->post($a['post_id']); $thread=$this->postThread($post['thread_id']);
                if (!self::eligible($post,$thread)) throw new RuntimeException('This post requires #railspike.');
                $row=$this->store->forPost($post['post_id']);
                if ($row===null) {
                    $q=$this->readModel->prepare('SELECT post_id, body, author_label FROM posts WHERE thread_id=? ORDER BY created_at, post_id LIMIT 100'); $q->execute([$post['thread_id']]);
                    $row=['id'=>bin2hex(random_bytes(16)), 'post_id'=>$post['post_id'],'thread_id'=>$post['thread_id'], 'version'=>1,
                        'state'=>'queued','request'=>$post['body'],'context'=>$q->fetchAll(PDO::FETCH_ASSOC),'history'=>[], 'revisions'=>[], 'requested_by'=>$actor['identity_id'], 'created_at'=>gmdate('c')];
                    $this->store->save($row);
                }
            } else {
                $row=$this->store->requireVersion((string)$a['workflow_id'], (int)$a['version']);
                $row=$this->transition($row,$a,$actor);
                $row['version']++; $this->store->save($row);
            }
            $this->store->record($receipt,$row['id'],$text,$signature);
            return $row;
        });
    }

    private function transition(array $row, array $action, array $actor): array
    {
        throw new RuntimeException('This workflow action is not available yet.');
    }

    private function post(string $id): array
    {
        $q=$this->readModel->prepare('SELECT * FROM posts WHERE post_id=?'); $q->execute([$id]); $p=$q->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new RuntimeException('Post not found.'); return $p;
    }
    private function postThread(string $id): array
    {
        $q=$this->readModel->prepare('SELECT * FROM threads WHERE root_post_id=?'); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
