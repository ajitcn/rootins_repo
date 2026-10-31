<?php
namespace App\Modules\Shopify\Controllers;
use App\Services\Shopify\ShopifyAiAnalysisCallbackService;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class AiAnalysisCallback extends \App\Controllers\BaseController
{
    public function request():ResponseInterface
    {
        try{$query=$this->request->getJSON(true);$query=is_array($query)?$query:$this->request->getPost();$context=(new ShopifyProxyService())->verify('ai_analysis.callback_request',$this->request->getGet(),(string)$this->request->getUri()->getQuery());$data=(new ShopifyAiAnalysisCallbackService())->submit($context,$query);return $this->response->setJSON(['status'=>true,'message'=>$data['message'],'data'=>$data]);}
        catch(InvalidArgumentException $e){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'message'=>$e->getMessage()]);}
        catch(RuntimeException $e){$code=$e->getMessage()==='AI_ANALYSIS_NOT_FOUND'?404:503;return $this->response->setStatusCode($code)->setJSON(['status'=>false,'message'=>$e->getMessage()]);}
        catch(\Throwable $e){log_message('error','Shopify AI callback request failed '.get_class($e));return $this->response->setStatusCode(503)->setJSON(['status'=>false,'message'=>'Callback request could not be recorded safely.']);}
    }
}
