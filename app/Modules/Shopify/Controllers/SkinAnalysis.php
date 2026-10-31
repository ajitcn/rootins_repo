<?php
namespace App\Modules\Shopify\Controllers;
use App\Services\Shopify\ShopifyProxyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifySkinAnalysisService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class SkinAnalysis extends \App\Controllers\BaseController
{
    public function submit(): ResponseInterface
    {
        try{$key=trim($this->request->getHeaderLine('Idempotency-Key'));$context=(new ShopifyProxyService())->verify('skin_analysis.submit',$this->request->getGet(),(string)$this->request->getUri()->getQuery());return $this->response->setJSON(['status'=>true,'data'=>(new ShopifySkinAnalysisService())->submit($context,$this->request->getPost(),$this->files(),$key)]);}
        catch(ShopifyProxyAuthenticationException $e){return $this->response->setStatusCode($e->httpStatus)->setJSON(['status'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()]);}
        catch(InvalidArgumentException $e){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'code'=>'SHOPIFY_SKIN_ANALYSIS_INVALID_REQUEST','message'=>$e->getMessage()]);}
        catch(RuntimeException $e){return $this->response->setStatusCode($e->getMessage()==='SHOPIFY_SKIN_ANALYSIS_NOT_FOUND'?404:503)->setJSON(['status'=>false,'code'=>$e->getMessage(),'message'=>'Skin analysis could not be processed safely.']);}
        catch(\Throwable $e){log_message('error','Shopify skin analysis boundary failure '.get_class($e));return $this->response->setStatusCode(503)->setJSON(['status'=>false,'code'=>'SHOPIFY_SKIN_ANALYSIS_UNAVAILABLE']);}
    }
    public function status(): ResponseInterface
    {
        try{$query=$this->request->getGet();$context=(new ShopifyProxyService())->verify('skin_analysis.status',$query,(string)$this->request->getUri()->getQuery());$reference=trim((string)($query['reference']??''));if($reference==='')throw new InvalidArgumentException('A skin analysis reference is required.');return $this->response->setJSON(['status'=>true,'data'=>(new ShopifySkinAnalysisService())->status($context,$reference)]);}
        catch(ShopifyProxyAuthenticationException $e){return $this->response->setStatusCode($e->httpStatus)->setJSON(['status'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()]);}
        catch(InvalidArgumentException $e){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'code'=>'SHOPIFY_SKIN_ANALYSIS_INVALID_REQUEST','message'=>$e->getMessage()]);}
        catch(RuntimeException $e){return $this->response->setStatusCode($e->getMessage()==='SHOPIFY_SKIN_ANALYSIS_NOT_FOUND'?404:503)->setJSON(['status'=>false,'code'=>$e->getMessage()]);}
        catch(\Throwable $e){return $this->response->setStatusCode(503)->setJSON(['status'=>false,'code'=>'SHOPIFY_SKIN_ANALYSIS_UNAVAILABLE']);}
    }
    private function files():array{$files=$this->request->getFileMultiple('photos');if(!is_array($files)||$files===[]){$files=[];foreach(['front','left','right','back','closeup'] as $angle){$file=$this->request->getFile('photo_'.$angle);if($file instanceof UploadedFile)$files[]=$file;}}return array_values(array_filter($files,static fn($file):bool=>$file instanceof UploadedFile));}
}
