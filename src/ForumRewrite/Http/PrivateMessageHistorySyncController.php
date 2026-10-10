<?php
declare(strict_types=1);
namespace ForumRewrite\Http;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
final class PrivateMessageHistorySyncController
{
    public function __construct(private readonly RouteServices $routes, private readonly Closure $viewer, private readonly Closure $service) {}
    public function handle(string $method, string $action, array $query): void
    {
        $viewer=($this->viewer)();
        if (!$viewer || ($viewer['is_approved']??0)!=1) { $this->respond(['status'=>'error','error'=>'Approved authentication required.'], $viewer?403:401); return; }
        if (!in_array([$method,$action],[['GET','work'],['GET','transfers'],['POST','transfers'],['POST','acknowledge']],true)) { $this->respond(['status'=>'error','error'=>'Method not allowed.'],405); return; }
        try {
            if ($method==='POST') {
                if (($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='ForumPrivateMessages'
                    || !str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')
                    || in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'',['cross-site','same-site'],true)) {
                    $this->respond(['status'=>'error','error'=>'Same-origin JSON required.'],403);return;
                }
                if((int)($_SERVER['CONTENT_LENGTH']??0)>100000) throw new InvalidArgumentException('History request too large.');
            }
            $service=($this->service)();
            $input=$query;
            if ($method==='POST') {
                $raw=file_get_contents('php://input',false,null,0,100001);
                if ($raw===false || strlen($raw)>100000) throw new InvalidArgumentException('History request too large.');
                try { $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR); }
                catch (\JsonException $e) { throw new InvalidArgumentException('Invalid history JSON.'); }
                if(!is_array($input) || array_is_list($input)) throw new InvalidArgumentException('Invalid history request.');
            }
            $result=match([$method,$action]) {
                ['GET','work']=>$service->work($viewer,$query['mode']??'account'),
                ['GET','transfers']=>$service->transfers($viewer,$input),
                ['POST','transfers']=>$service->upload($viewer,$input),
                ['POST','acknowledge']=>$service->acknowledge($viewer,$input),
            };
            $this->respond(['status'=>'ok']+$result,200);
        }
        catch (InvalidArgumentException $e) { $this->respond(['status'=>'error','error'=>$e->getMessage()],400); }
        catch (RuntimeException $e) { $this->respond(['status'=>'error','error'=>'History synchronization unavailable. Retry later.'],503); }
        catch (Throwable $e) { $this->respond(['status'=>'error','error'=>'History synchronization unavailable. Retry later.'],503); }
    }
    private function respond(array $payload, int $status): void { $this->routes->sendJson($payload,$status,$this->routes->noStoreHeaders()); }
}
