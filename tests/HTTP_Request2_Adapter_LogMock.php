<?php
require_once 'HTTP/Request2/Adapter/Mock.php';

class HTTP_Request2_Adapter_LogMock extends HTTP_Request2_Adapter_Mock
{
    public $requestedUrls = array();

    public function sendRequest(HTTP_Request2 $request)
    {
        $this->requestedUrls[] =  (string)$request->getUrl();
        return parent::sendRequest($request);
    }
}

?>