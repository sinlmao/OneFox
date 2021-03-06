<?php

/**
 * @author ryan<zer0131@vip.qq.com>
 * @desc 默认控制器
 */
namespace controller\index;

use controller\Base;
use lib\Test\Test;
use Service\Service;

class Index extends Base {
    
    /**
     * 默认方法
     */
    public function indexAction(){
		$this->show();
    }

    public function testAction() {
        Test::test();
    }

    public function serviceAction() {
        Service::composerTest();
    }
}
