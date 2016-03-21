<?php

namespace Controller\Api;

use OneFox\ApiController;

class TokenController extends ApiController {
    
    public function indexAction(){
        $param = $this->get('test');
        if (!$param) {
            $this->json(self::CODE_FAIL, 'error');
        }
        $this->json(self::CODE_SUCCESS, 'ok', array('test'=>$param));
    }
    
}

