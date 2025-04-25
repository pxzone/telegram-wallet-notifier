<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_api {

    public function authKeys() {
        $auth = array(
            'wallet_address_notifier_api' => 'api_key',
        );
        return $auth;
    }
}
