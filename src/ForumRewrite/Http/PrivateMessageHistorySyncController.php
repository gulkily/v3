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
        if ($method!=='GET' || $action!=='work') { $this->respond(['status'=>'error','error'=>'Method not allowed.'],405); return; }
        try { $result=($this->service)()->work($viewer); $this->respond(['status'=>'ok']+$result,200); }
        catch (InvalidArgumentException $e) { $this->respond(['status'=>'error','error'=>$e->getMessage()],400); }
        catch (RuntimeException $e) { $this->respond(['status'=>'error','error'=>'History synchronization unavailable. Retry later.'],503); }
        catch (Throwable $e) { $this->respond(['status'=>'error','error'=>'History synchronization unavailable. Retry later.'],503); }
    }
    private function respond(array $payload, int $status): void { $this->routes->sendJson($payload,$status,$this->routes->noStoreHeaders()); }
}
