<?php
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('Asia/Manila');

class Wallet_address_notifier_bot extends CI_Controller {

    function __construct(){
        parent::__construct();
        $this->load->library('telegram_api');
        $this->load->model('Wallet_notifier_bot_model');
    }
    public function webhook() 
    {
        // Get the incoming update
        $update = json_decode(file_get_contents("php://input"), TRUE);

        // Handle the update
        if (isset($update['message'])) 
        {
            $name = $update['message']['chat']['first_name'];
            $chat_id = $update['message']['chat']['id'];
            $message_text = $update['message']['text'];
            $message_id = $update['message']['message_id'];
            $user_state = $this->Wallet_notifier_bot_model->getUserState($chat_id);

            if ($message_text == '/start') 
            {
                $this->Wallet_notifier_bot_model->registerUser($chat_id); // register user
                $this->startCommand($chat_id, $message_id, $name, 'send');
            }
            else if ($update['message']['text'] == '/menu') 
            {
                $this->startCommand($chat_id, $message_id, $name, 'send');
            }
            
            else if ($message_text !== '/start' && stripos($user_state, 'waiting_for_wallet_label:') !== FALSE) // waiting for wallet address label
            {
                $str_parts = explode(':', $user_state);
                $wallet_id = $str_parts[1];

                $wallet_data = $this->Wallet_notifier_bot_model->getWalletDataByID($wallet_id);
                $this->Wallet_notifier_bot_model->insertWalletLabel($wallet_id, $message_text);

                $response_text = "✅ Label added for wallet address<b>\n\n".$wallet_data['wallet_address'].'</b>.';
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'parse_mode'=> 'html',
                );
                $this->sendMessage($post_data);

                $this->addressList($chat_id, $message_id, 'send');
                $this->Wallet_notifier_bot_model->deleteUserState($chat_id);

            }
            else if ($message_text !== '/start' && $user_state == 'add_new_wallet') // adding new wallet address
            {   
                $wallet_address = $message_text;
                $is_valid = $this->isAddressValid($wallet_address);
                if($is_valid)
                {   
                    $check_address = $this->Wallet_notifier_bot_model->checkAddress($chat_id, $wallet_address);
                    $status = 0;

                    if ($check_address > 0)
                    {
                        $response_text2 = "❗️ Wallet address already added! Try again.";
                    }
                    else 
                    {
                        $response_text1 = "Adding. Please wait...";
                        $post_data_waiting = array(
                            'message_id' => $message_id,
                            'chat_id' => $chat_id,
                            'text' => $response_text1,
                            'parse_mode'=> 'html',
                        );
                        $this->sendMessage($post_data_waiting);

                        $status = 1;
                        $wallet_id = $this->Wallet_notifier_bot_model->registerWalletNotifierData($chat_id, $wallet_address);
                        $this->Wallet_notifier_bot_model->registerUser($chat_id); // register user
                        $this->getTxnRecord($wallet_id, $wallet_address);

                        $response_text2 = "✅ Wallet address added!\n\n$wallet_address";
                        $this->Wallet_notifier_bot_model->deleteUserState($chat_id);
                        $this->getWalletAddressBalance($wallet_address);
                    }

                    $post_data = array(
                        'chat_id' => $chat_id,
                        'text' => $response_text2,
                        'parse_mode'=> 'html',
                    );
                    $this->sendMessage($post_data);

                    if($status)
                    {
                        $this->addressList($chat_id, $message_id, 'send');
                    }
                    else {
                        $response_text = "🖋 Enter your wallet address.";
                        $post_data_try_again = array(
                            // 'message_id' => $message_id,
                            'chat_id' => $chat_id,
                            'text' => $response_text,
                            // 'reply_to_message_id' => $message_id,
                            'parse_mode'=> 'html',
                        );
                        $this->sendMessage($post_data_try_again);
                    }
                }
                else 
                {
                    $response_text = "⚠️ Invalid wallet address! Try again.";
                    $post_data_try_again = array(
                        'chat_id' => $chat_id,
                        'text' => $response_text,
                        'parse_mode'=> 'html',
                    );
                    $this->sendMessage($post_data_try_again);
                }
            }
            else
            {
                $response_text = "Unrecognized command. Say what?";
                $post_data_try_again = array(
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                );
                $this->sendMessage($post_data_try_again); 
            }
        }

        // Callback using buttons
        else if (isset($update['callback_query'])) 
        {   
            $callback_data = $update['callback_query']['data'];
            $message_id = $update['callback_query']['message']['message_id'];
            $message_text = $update['callback_query']['message']['text'];
            $chat_id = $update['callback_query']['message']['chat']['id'];
            $name = $update['callback_query']['message']['chat']['first_name'];

            if ($callback_data === 'add_new_wallet') 
            {
                $this->Wallet_notifier_bot_model->insertUserState($chat_id, 'add_new_wallet');

                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => '↩️ Cancel', 'callback_data' => 'go_back')
                        ),
                    )  
                );
                $encoded_keyboard = json_encode($keyboard);

                $response_text = "🖋 Enter your wallet address.";
                $post_data = array(
                    'message_id' =>$message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'reply_markup' => $encoded_keyboard,
                    'parse_mode'=> 'html',
                );
                $this->editMessageText($post_data);
            }
           
            else if ($callback_data === 'address_list') 
            {
                $this->addressList($chat_id, $message_id, 'edit');
            }
            else if ($callback_data === 'go_back') 
            {
                $this->startCommand($chat_id, $message_id, $name, 'edit');
            }
            else if ($callback_data === 'go_back_address_list') 
            {
                $this->addressList($chat_id, $message_id, 'edit');
            }
            else if (stripos($callback_data, 'yes_del_add:') !== FALSE ) // delete wallet address
            {
                $str_parts = explode(':', $callback_data);
                $wallet_id = $str_parts[1]; 
                $wallet_data = $this->Wallet_notifier_bot_model->getWalletDataByID($wallet_id);
                $this->Wallet_notifier_bot_model->deleteWalletAddress($wallet_id);
                
                $response_text = "✅ Wallet address successfully removed \n\n".$wallet_data['wallet_address'];
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'parse_mode'=> 'html',
                );
                $this->editMessageText($post_data);

                $this->addressList($chat_id, $message_id, 'send');
            }
            else if (stripos($callback_data, 'add_label:') !== FALSE ) // delete wallet address
            {
                $str_parts = explode(':', $callback_data);
                $wallet_id = $str_parts[1]; 

                $wallet_data = $this->Wallet_notifier_bot_model->getWalletDataByID($wallet_id);
                $this->Wallet_notifier_bot_model->insertUserState($chat_id, 'waiting_for_wallet_label:'.$wallet_id);

                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => '↩️ Cancel', 'callback_data' => "wallet:$wallet_id")
                        ),
                    )  
                );
                $encoded_keyboard = json_encode($keyboard);

                $response_text = "<b>Enter a label for wallet address</b> \n\n".$wallet_data['wallet_address'];
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'reply_markup' => $encoded_keyboard,
                    'parse_mode'=> 'html',
                );
                $this->editMessageText($post_data);
            }
            else if (stripos($callback_data, 'wallet:') !== FALSE ) // selected wallet address
            {
                sleep(1);
                $str_parts = explode(':', $callback_data);
                $wallet_id = $str_parts[1];
                $wallet_data = $this->Wallet_notifier_bot_model->getWalletDataByID($wallet_id);
                $label = '';
                if(!empty($wallet_data['label'])){
                    $label = "\nLabel: ".$wallet_data['label'];
                }

                $user_settings = $this->Wallet_notifier_bot_model->getUserSettings($chat_id);
                $wallet_address = $wallet_data['wallet_address'];
                // $wallet_balance_data = $this->getWalletAddressBalance($wallet_address);
                
                $balance = '0.00';
                if($wallet_data['btc_balance'] > 0){
                    $balance = rtrim(number_format($wallet_data['btc_balance'], 10), '0');
                }
                

                $fiat_value = $wallet_data['fiat_value'];
                $coin = $wallet_data['coin'];
                $explorer_url = $user_settings['address_url'].$wallet_address;
                $explorer_name = $user_settings['explorer_name'];
                

                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => '🔗 Check Address', 'url' => $explorer_url),
                            array('text' => '🏷 Add/Edit Label', 'callback_data' => "add_label:$wallet_id"),
                        ),
                        array(
                            array('text' => '🗑 Remove Wallet', 'callback_data' => "yes_del_add:$wallet_id"),
                            array('text' => '↩️ Go Back', 'callback_data' => 'go_back_address_list')
                        )
                    )  
                );
                $encoded_keyboard = json_encode($keyboard);
                $response_text = "<b>Selected Wallet Address </b>\n$label\nBalance: $balance ".strtoupper($coin)." • $fiat_value\nAddress: $wallet_address\n";
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'parse_mode'=> 'html',
                    'reply_markup' => $encoded_keyboard,
                );
                $this->editMessageText($post_data);
                
            }
            
            else if ($callback_data === 'setting_explorer') {
                $this->blockExplorers($chat_id, $message_id, 'edit');
            }
            else if (stripos($callback_data, 'choose_explorer:') !== FALSE ) {
                $str_parts = explode(':', $callback_data);
                $explorer_id = $str_parts[1];
                $explorer_name = $str_parts[2];
                $this->Wallet_notifier_bot_model->updateUserExplorer($explorer_id, $chat_id);
                
                $response_text = "✅ Block Explorer Updated to <b>$explorer_name</b>";
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'parse_mode' => 'html',
                );
                $this->editMessageText($post_data);

                $this->blockExplorers($chat_id, $message_id, 'send');
            }
            else if ($callback_data === 'about') {
                $response_text = "More information about the bot.";

                $keyboard = array(
                    'inline_keyboard' => array(
                        
                        array(
                            array('text' => '💝 Donate', 'callback_data' => 'donation'),
                        ),
                        array(
                            array('text' => '🔖 Topic', 'url' => 'https://bitcointalk.org/index.php?topic=5522781'),
                        ),
                        array(
                            array('text' => '↩️ Go Back', 'callback_data' => 'go_back')
                        ),
                    )
                );
                $encoded_keyboard = json_encode($keyboard);

                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'reply_markup' => $encoded_keyboard,
                );
                $this->editMessageText($post_data);
            }

            else if ($callback_data === 'donation') {
                $response_text = "All donations will go towards the server/domain expense.";

                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => 'Bitcoin (bech32)', 'callback_data' => 'btc_bech32'),
                        ),
                        array(
                            array('text' => 'Ethereum', 'callback_data' => 'eth_address'),
                        ),
                        array(
                            array('text' => 'USDT (TRC20)', 'callback_data' => 'usdt_trc20'),
                        ),
                        array(
                            array('text' => 'XMR', 'callback_data' => 'xmr_address'),
                        ),
                        array(
                            array('text' => '↩️ Go Back', 'callback_data' => 'go_back')
                        ),
                    )
                );
                $encoded_keyboard = json_encode($keyboard);

                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'reply_markup' => $encoded_keyboard,
                );
                $this->editMessageText($post_data);
            }
            else if ($callback_data === 'xmr_address') {
                $response_text = "45eoPvxBkZeJ2nSQHGd9VRCeSvdmKcaV35tbjmprKa13UWVgFzArNR1PWNrZ9W4XwME3iJB9gzMKuSqGc2EWR4ZCTX66NAV";
                $post_data = array(
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                );
                $this->sendMessage($post_data);
            }
            else if ($callback_data === 'usdt_trc20') {
                $response_text = "TWyvoyijQY2mhnpUMY4bmpk3fX8A66KZTX";
                $post_data = array(
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                );
                $this->sendMessage($post_data);
            }
            else if ($callback_data === 'eth_address') {
                $response_text = "0x6e212cB02e53c7d53b84277ecC7A923601422a46";
                $post_data = array(
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                );
                $this->sendMessage($post_data);
            }
            else if ($callback_data === 'btc_bech32') {
                $response_text = "bc1q00pxz0k04ndxqdvmkr8kj3fwtlntfctlzp37xl";
                $post_data = array(
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                );
                $this->sendMessage($post_data);
            }
            else if ($callback_data === 'admin_stat') {
                $user_count = $this->Wallet_notifier_bot_model->getActiveUserCount();
                $wallet_address_count = $this->Wallet_notifier_bot_model->getAllWalletAddressCount();
                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => '↩️ Go back', 'callback_data' => 'go_back')
                        ),
                    )  
                );
                $encoded_keyboard = json_encode($keyboard);
                $response_text = "<b>📊 Statistics</b>\n\nUser count: $user_count\nWallet address count: $wallet_address_count\n";
                $post_data = array(
                    'message_id' => $message_id,
                    'chat_id' => $chat_id,
                    'text' => $response_text,
                    'parse_mode' => 'html',
                    'reply_markup' => $encoded_keyboard,
                );
                $this->editMessageText($post_data);
            }
           
        }
    }
    private function startCommand($chat_id, $message_id, $name, $type)
    {
        $response_text = "Hello $name! \n\nWelcome to Wallet Transaction Notifier Bot!\n\nHere's what you can do";
        if ($chat_id == "XXXXXX")
        {
            $keyboard = array(
                'inline_keyboard' => array(
                    array(
                        array('text' => '🖋 Add Wallet Address', 'callback_data' => 'add_new_wallet')
                    ),
                    array(
                        array('text' => '📑 Wallet Address List', 'callback_data' => 'address_list')
                    ),
                    array(
                        array('text' => '🔍 Block Explorers', 'callback_data' => 'setting_explorer')
                    ),
                    array(
                        array('text' => '📊 Statistics', 'callback_data' => 'admin_stat')
                    ),
                    array(
                        array('text' => '💡 About', 'callback_data' => 'about')
                    )
                )
            );
        }
        else
        {
            $keyboard = array(
                'inline_keyboard' => array(
                    array(
                        array('text' => '🖋 Add Wallet Address', 'callback_data' => 'add_new_wallet')
                    ),
                    array(
                        array('text' => '📑 Wallet Address List', 'callback_data' => 'address_list')
                    ),
                    array(
                        array('text' => '🔍 Block Explorers', 'callback_data' => 'setting_explorer')
                    ),
                    array(
                        array('text' => '💡 About', 'callback_data' => 'about')
                    )
                )
            );
        }
        
        $encoded_keyboard = json_encode($keyboard);
        if($type == 'edit')
        {
            $post_data = array(
                'message_id' => $message_id,
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
            );
            $this->editMessageText($post_data);
        }
        else 
        {
            $post_data = array(
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
            );
            $this->sendMessage($post_data);
        }
    }
    private function blockExplorers($chat_id, $message_id, $type)
    {
        $user_settings = $this->Wallet_notifier_bot_model->getUserSettings($chat_id);
        $explorer_data = $this->Wallet_notifier_bot_model->getWalletExplorer();
        $explorers = array();
        foreach ($explorer_data as $ed) {    
            $row_array = array('text' => $ed['name'], 'callback_data' => 'choose_explorer:'.$ed['id'].':'.$ed['name']);
            array_push($explorers, $row_array);
        }
        $keyboard = array(
            'inline_keyboard' => array()
        );
        foreach ($explorers as $explorer) {
            $keyboard['inline_keyboard'][] = array($explorer);
        }
        
        $additionalCommands = array(
            array('text' => '↩️ Go Back', 'callback_data' => 'go_back')
        );
        $explorer_name = $user_settings['explorer_name'];
        if(empty($user_settings['explorer_name'])) {
            $explorer_name = "Mempool";
        }
        
        $keyboard['inline_keyboard'][] = $additionalCommands;
        $encoded_keyboard = json_encode($keyboard);
        $response_text = "<b>🔍 Block Explorer List.</b>\n\nChoose your Block Explorer.\n\nCurrent Explorer: <b>".$explorer_name.'</b>';
        if($type == 'edit'){
            $post_data = array(
                'message_id' => $message_id,
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
                'parse_mode'=> 'html',
            );
            $this->editMessageText($post_data);
        }
        else{
            $post_data = array(
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
                'parse_mode'=> 'html',
            );
            $this->sendMessage($post_data);
        }
    }
    private function addressList($chat_id, $message_id, $type)
    {
        $this->db->reconnect();
        $wallet_address = $this->Wallet_notifier_bot_model->getWalletAddress($chat_id);
        $wallets = array();
        foreach ($wallet_address as $wa) {    
            $first = substr($wa['wallet_address'], 0, 7);
            $last = substr($wa['wallet_address'], -6);
            $balance = rtrim(number_format($wa['btc_balance'], 10), '0');
            if($balance <= 0) {
                $balance = '0.00';
            }
            $wallet_label = $first.'.....'.$last . '    ' . $balance. ' ' . strtoupper($wa['coin']) ;
            if(!empty($wa['label']))
            {
                $wallet_label = $wa['label'] . '    ' . $balance. ' ' . strtoupper($wa['coin']);
            }  
            $row_array = array('text' => $wallet_label, 'callback_data' => 'wallet:'.$wa['id']);
            array_push($wallets, $row_array);
        }
        $keyboard = array(
            'inline_keyboard' => array()
        );
        foreach ($wallets as $wallet) {
            $keyboard['inline_keyboard'][] = array($wallet);
        }
        
        $additionalCommands = array(
            array('text' => '🖋 Add new address', 'callback_data' => 'add_new_wallet'),
            array('text' => '↩️ Go Back', 'callback_data' => 'go_back')
        );
        $keyboard['inline_keyboard'][] = $additionalCommands;
        $encoded_keyboard = json_encode($keyboard);
        $response_text = "<b>📑 Wallet Address List</b>\n\nAdd or remove wallet address and get notified for every new transactions.";
        if($type == 'edit'){
            $post_data = array(
                'message_id' => $message_id,
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
                'parse_mode'=> 'html',
            );
            $this->editMessageText($post_data);
        }
        else{
            $post_data = array(
                'chat_id' => $chat_id,
                'text' => $response_text,
                'reply_markup' => $encoded_keyboard,
                'parse_mode'=> 'html',
            );
            $this->sendMessage($post_data);
        }
    }
    public function isValidBitcoinAddress($address) {
        $pattern = '/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,90}$/i';
        return preg_match($pattern, $address) === 1;
    }
    public function sendMessage($post_data)
    {
        $telegram_api = $this->telegram_api->authKeys();
        $bot_token = $telegram_api['wallet_address_notifier_api'];
        $api_endpoint = "https://api.telegram.org/bot$bot_token/sendMessage";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }
    public function editMessageText($post_data){
        $telegram_api = $this->telegram_api->authKeys();
        $bot_token = $telegram_api['wallet_address_notifier_api'];
        $api_endpoint = "https://api.telegram.org/bot$bot_token/editMessageText";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }
    public function deleteMessage($chat_id, $message_id){
        $telegram_api = $this->telegram_api->authKeys();
        $bot_token = $telegram_api['wallet_address_notifier_api'];
        $api_endpoint_delete = "https://api.telegram.org/bot$bot_token/deleteMessage";
        $post_data_delete = array(
            'chat_id' => $chat_id,
            'message_id' => $message_id
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint_delete);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_delete);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }
    
    public function isAddressValid($address)
    {
        $api_url = "https://mempool.space/api/v1/validate-address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $data = json_decode($response, true);
            if($data['isvalid'] == true){
                return true;
            }
        }
        else{
            return null;
        }
    }
    public function getTxnRecord($wallet_id, $address)
    {
        $api_url = "https://mempool.space/api/address/$address/txs/chain"; // only confirmed transactions
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $fetched_data = json_decode($response, true);

            if(!empty($fetched_data))
            {
                $tx_count = $this->getConfirmedTxnNumber($wallet_id, $address);
                $latest_txid = $fetched_data[0]['txid'];
                $data_arr = array(
                    'wallet_id'=>$wallet_id,
                    'txn_count'=>$tx_count,
                    'txid'=>$latest_txid,
                );
                $this->Wallet_notifier_bot_model->updateWalletAddressList($wallet_id, $data_arr);

                foreach($fetched_data as $fd)
                {
                    $txids = $fd['txid'];
                    $this->Wallet_notifier_bot_model->insertWalletAddressLog($wallet_id, $txids);
                }
            }
            
        }
        else{
            return false;
        }
    }
    public function getConfirmedTxnNumber($wallet_id, $address)
    {
        $api_url = "https://mempool.space/api/address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $data = json_decode($response, true);
            $txn_count = $data['chain_stats']['tx_count']; // confirmed transaction tx_count
           return $txn_count;
        }
        else{
            return false;
        }
    }
    # cron job * * * * * - every 1 mins to check if there's new transaction
    public function checkNewTransaction()
    {
        $ip_address = $this->input->ip_address();
        $ip_whitelisted = array(
            '159.100.18.2',
        );
        $allowed = true;
        if (in_array($ip_address, $ip_whitelisted)) {
            $allowed = true;
        } 

        if($allowed)
        {
            # LIMIT PROCESS AND PER BATCH
            // $total_rows = $this->Wallet_notifier_bot_model->getAllWalletAddressCount(); 
            // $rows_per_page = 20;
            // $page_no = ceil($total_rows / $rows_per_page);

            // for ($i = 1; $i <= $page_no; $i++) {
            //     // $row_array = $this->function2($i); // Pass the page number
            //     $this->getNewTransactionLimit($rows_per_page, $i);
            //     $delay = rand(2, 3); 
            //     sleep($delay);
            // }
           
            

            // log_message('info', 'CLI SCRAPER: checkNewTransaction() triggered.');

            # OLD PROCESS
            $wallet_data = $this->Wallet_notifier_bot_model->getAllWalletAddress();
            $fiat_bitcoin_value = $this->getFiatBitcoinValue();
            if(!empty($wallet_data))
            {
                foreach($wallet_data as $wallet)
                {
                    $wallet_address = $wallet['wallet_address'];
                    $wallet_id = $wallet['id'];
                    $latest_txn_record = $this->getLatestTxnRecord($wallet_id, $wallet_address, $wallet['txn_count']); // get latest transacstion using mempool api
                    $latest_txn_count = $latest_txn_record['txn_count'];
                    $wallet_txn_count = $wallet['txn_count'];

                    $final_balance = $latest_txn_record['final_balance'] .' '. strtoupper($wallet['coin']);
                    $final_fiat_value = $final_balance * (int)$fiat_bitcoin_value;
                    $final_fiat_value = number_format($final_fiat_value, 2) . ' USD';

                    // check if if there's new transaction
                    if((int)$latest_txn_count > (int)$wallet_txn_count) //  && $latest_txn_record['latest_txid'] !== $wallet['last_txid']
                    {

                        $new_txids = $latest_txn_record['new_txids'];
                        foreach ($new_txids as $new_txid)
                        {
                            $latest_txid = $new_txid['txid'];
                            $this->db->reconnect();
                            $txid_exists = $this->Wallet_notifier_bot_model->checkTxidExists($latest_txid, $wallet_id); // if txid is existing

                            if($txid_exists <= 0)
                            { // start if $txid_exists not exists
                                $total_receive = 0;
                                $total_spent = 0;
                                $label = '';
                                if(!empty($wallet['label'])){
                                    $label = "<b>Label</b>: ".$wallet['label']."\n";
                                }

                                foreach($new_txid['vin'] as $vin) // outgoing
                                {
                                    if ($wallet['wallet_address'] == $vin['prevout']['scriptpubkey_address'])
                                    {
                                        $total_spent += $vin['prevout']['value'];
                                    }
                                }
                                foreach($new_txid['vout'] as $vout) // incoming
                                {
                                    if ($wallet['wallet_address'] == $vout['scriptpubkey_address']) 
                                    {
                                        $total_receive += $vout['value'];
                                    }
                                }

                                $type = ($total_spent > 0) ? "Outgoing" : "Incoming";
                                $amount = ($total_spent > 0) ? $total_spent : $total_receive;

                                $amount = $amount / 100000000;
                                $amount = rtrim(number_format($amount, 10), '0');

                                $fiat_value = $amount * (int)$fiat_bitcoin_value;
                                $fiat_value = $fiat_value ? number_format($fiat_value, 2) . ' USD' : '0.00 USD';
                                $crypto_value = rtrim($amount, '.') ." ". strtoupper($wallet['coin']);

                                // SEND NOTIFICATION FOR  UNCONFIRMED AND CONFIRMED TXS
                                $response_text = "🔔 New $type Transaction\n<blockquote>$label<b>Wallet Address</b>: $wallet_address\n<b>Amount</b>: $crypto_value • $fiat_value\n<b>Balance</b>: $final_balance • $final_fiat_value\n<b>Txid</b>: $latest_txid</blockquote>";
                                $notify = $this->notifyUser($wallet['chat_id'], $response_text, $latest_txid);
                                $this->Wallet_notifier_bot_model->insertWalletNotifierTgLogs($wallet['chat_id'], $wallet_id, $type, $latest_txid);

                                // Add nex txid and update wallet address data
                                $this->Wallet_notifier_bot_model->addNewTransactionLog($wallet_id, $latest_txid);
                                $this->Wallet_notifier_bot_model->updateWalletAddressData($wallet_id, $latest_txid, $latest_txn_count);
                                $this->Wallet_notifier_bot_model->insertWalletBalance($address, $final_balance, $fiat_value);
                                
                            } // end $txid_exists
                            else if($txid_exists > 0){
                                $this->Wallet_notifier_bot_model->updateWalletAddressData($wallet_id, $latest_txid, $latest_txn_count);
                            }
                          
                        }
                    }

                    $delay = rand(2, 4); 
                    sleep($delay);
                }
            }

            $data_response = array(
                'status'=>200,
                'wallet_count'=>count($wallet_data)
            );

        }
        else if($allowed !== true){
            $this->Wallet_notifier_bot_model->insertSystemActivityLog("Access wallet notifier. Action not allowed.");
            $data_response = array(
                'response' => 'Action not allowed!'
            );
            // $this->output->set_content_type('application/json')->set_output(json_encode($data_response));
            exit();
        }
    }
    public function getLatestTxnRecord($wallet_id, $address, $txn_count)
    {
        # $txn_count = confirmed txn count store in db upon registration
        $api_url = "https://mempool.space/api/address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $fetched_data = json_decode($response, true);
            $confirmed_txn_count = $fetched_data['chain_stats']['tx_count']; // confirmed transaction tx_count
            $unconfirmed_txn_count = $fetched_data['mempool_stats']['tx_count']; // unconfirmed transaction tx_count
            $total_txn_count = $confirmed_txn_count + $unconfirmed_txn_count;

            // Get confirmed balance
            $confirmed_funded = $fetched_data['chain_stats']['funded_txo_sum'];
            $confirmed_spent  = $fetched_data['chain_stats']['spent_txo_sum'];
            $confirmed_balance = $confirmed_funded - $confirmed_spent;

            // Get mempool stats
            $mempool_incoming = $fetched_data['mempool_stats']['funded_txo_sum'];
            $mempool_outgoing = $fetched_data['mempool_stats']['spent_txo_sum'];

            $final_balance = $confirmed_balance;
            if ($mempool_incoming > 0) {
                $final_balance = $confirmed_balance + $mempool_incoming;
            } 
            else if ($mempool_outgoing > 0) {
                $final_balance = $confirmed_balance - $mempool_outgoing;
            }
            $final_balance = $final_balance / 100000000;
            $final_balance = rtrim(number_format($final_balance, 10), '0');
           
            # PROCESS
            # Get all the recent wallet address,
            # check new tx from  mempool api
            # notify subscriber

            if($unconfirmed_txn_count > 0)
            {
                $unconfirmed_tx = $this->getUnconfirmedTx($address);
                $latest_txn_data['new_txids'] = $unconfirmed_tx;
                $latest_txn_data['txn_count'] = $total_txn_count;
                $latest_txn_data['final_balance'] = $final_balance;
            }
            else{
                $confirmed_tx = $this->getConfirmedTx($address);
                $latest_txn_data['new_txids'] = $confirmed_tx;
                $latest_txn_data['txn_count'] = $total_txn_count;
                $latest_txn_data['final_balance'] = $final_balance;
            }
            return $latest_txn_data;
        }
        else{
            // $this->notifyAdminAPIStatus($httpCode);
            $response_text = "Wallet Address Notifier: Mempool API Status: $httpCode. Function getLatestTxnRecord().";
            $this->Wallet_notifier_bot_model->insertSystemActivityLog($response_text);
            return false;
        }
    }
    public function getUnconfirmedTx($address)
    {
        $api_url = "https://mempool.space/api/address/$address/txs/mempool";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $data = json_decode($response, true);
            return $data;
        }
        else{
            $response_text = "Wallet Address Notifier: Mempool API Status: $httpCode. Function getUnconfirmedTx()";
            $this->Wallet_notifier_bot_model->insertSystemActivityLog($response_text);
            return false;
        }
    }
    public function getConfirmedTx($address)
    {
        $api_url = "https://mempool.space/api/address/$address/txs/chain";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            # GET ONLY CONFIRMED TXS TODAY
            $txs = json_decode($response, true);

            date_default_timezone_set('Asia/Manila');
            $todayStart = strtotime("today");
            $todayEnd = strtotime("tomorrow") - 1;

            $todayTxs = [];
            foreach($txs as $tx){
                if (isset($tx['status']['block_time'])) {
                    $blockTime = $tx['status']['block_time'];
                    if ($blockTime >= $todayStart && $blockTime <= $todayEnd) {
                        $todayTxs[] = $tx;
                    }
                }
            }
            return $todayTxs;
        }
        else{
            $response_text = "Wallet Address Notifier: Mempool API Status: $httpCode. Function getConfirmedTx()";
            $this->Wallet_notifier_bot_model->insertSystemActivityLog($response_text);
            return false;
        }
    }
    public function getAllTxnNumber($address)
    {
        $api_url = "https://mempool.space/api/address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $data = json_decode($response, true);
            $chain_tx_count = $data['chain_stats']['tx_count']; // confirmed transaction tx_count
            $mempool_tx_count = $data['mempool_stats']['tx_count']; // unconfirmed transaction tx_count

            $txn_count = $chain_tx_count + $mempool_tx_count;
            return $txn_count;
        }
        else{
            return false;
        }
    }
    public function getConfirmedUnconfirmedTxnNumber($address)
    {
        $api_url = "https://mempool.space/api/address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $data = json_decode($response, true);
            $txn_data['confirmed_txn_count'] = $data['chain_stats']['tx_count']; // confirmed transaction tx_count
            $txn_data['unconfirmed_txn_count'] = $data['mempool_stats']['tx_count']; // unconfirmed transaction tx_count
            $txn_data['total_txn_count'] = $txn_data['confirmed_txn_count'] + $txn_data['unconfirmed_txn_count'];
            return $txn_data;
        }
        else{
            $response_text = "Wallet Address Notifier: Mempool API Status: $httpCode. Function getConfirmedUnconfirmedTxnNumber()";
            $this->Wallet_notifier_bot_model->insertSystemActivityLog($response_text);
            return false;
        }
    }
    public function notifyUser($chat_id, $response_text, $latest_txid)
    {
        $user_settings = $this->Wallet_notifier_bot_model->getUserSettings($chat_id);
        $explorer_url = $user_settings['txid_url'].$latest_txid;
        $explorer_name = $user_settings['explorer_name'];

        $telegram_api = $this->telegram_api->authKeys();
        $bot_token = $telegram_api['wallet_address_notifier_api'];
        $api_endpoint = "https://api.telegram.org/bot$bot_token/sendMessage";


        $keyboard = array(
            'inline_keyboard' => array(
                array(
                    array('text' => 'Check Transaction', 'url' => $explorer_url),
                )
            )
        );
        $encoded_keyboard = json_encode($keyboard);

        $post_data = array(
            'chat_id' => $chat_id,
            'text' => $response_text,
            'reply_markup' => $encoded_keyboard,
            'parse_mode'=> 'html',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        // return $response;
        // $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }
    
    // private function sendTelegramNotification($chatId, $message) {
    //     // Same as the previous sendMessage implementation
    // }
    

    public function getFiatValue($amount, $coin_name, $currency){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://mempool.space/api/v1/prices",
            
            // CURLOPT_URL => "https://api.coingecko.com/api/v3/simple/price?ids=$coin_name&vs_currencies=".$currency,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data_obj = json_decode($response);
        
        # USING COIGECKO API 
        // $coin_to_fiat = $data_obj->$coin_name->$currency;
        // $fiat_value = $amount * $coin_to_fiat;
        // return number_format($fiat_value, 2).' '. strtoupper($currency);

        #USING MEMPOOL.SPACE API
        $btc_usd = $data_obj->USD;
        $fiat_value = $amount * $btc_usd;
        $response = number_format($fiat_value, 2).' '. strtoupper($currency);
        return $response;
    }
    public function getFiatBitcoinValue(){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://mempool.space/api/v1/prices",
            
            // CURLOPT_URL => "https://api.coingecko.com/api/v3/simple/price?ids=$coin_name&vs_currencies=".$currency,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data_obj = json_decode($response);
      
        #USING MEMPOOL.SPACE API
        $btc_usd = $data_obj->USD;
        return $btc_usd;
    }

    public function notifyAdminAPIStatus($status_code)
    {
        $response_text = "Mempool API Status: $status_code";
        $post_data = array(
            'chat_id' => 'XXXX', // admin telegram
            'text' => $response_text,
            'parse_mode'=> 'html',
        );
        $this->sendMessage($post_data);
    }
    public function getWalletAddressBalance($address)
    {
        $api_url = "https://mempool.space/api/address/$address";
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        
        if ($httpCode == 200) 
        {
            $fetched_data = json_decode($res, true);
            
             // Get confirmed balance
             $confirmed_funded = $fetched_data['chain_stats']['funded_txo_sum'];
             $confirmed_spent  = $fetched_data['chain_stats']['spent_txo_sum'];
             $confirmed_balance = $confirmed_funded - $confirmed_spent;
 
             // Get mempool stats
             $mempool_incoming = $fetched_data['mempool_stats']['funded_txo_sum'];
             $mempool_outgoing = $fetched_data['mempool_stats']['spent_txo_sum'];
 
             $final_balance = $confirmed_balance;
             if ($mempool_incoming > 0) {
                 $final_balance = $confirmed_balance + $mempool_incoming;
             } 
             else if ($mempool_outgoing > 0) {
                 $final_balance = $confirmed_balance - $mempool_outgoing;
             }
             $balance = $final_balance / 100000000;

            if($balance > 0){
                $fiat_value = $this->getFiatValue($balance, 'bitcoin', 'usd'); // get bitcoin usd pair value
                $fiat_value = $fiat_value;
            }
            else{
                $balance = '0.00';
                $fiat_value = '0 USD';
            }

            $this->Wallet_notifier_bot_model->insertWalletBalance($address, $balance, $fiat_value);
            $response['status'] = $httpCode;
            $response['balance'] = $balance;
            $response['fiat_value'] = $fiat_value;
        }
        else{
            $response['status'] = false;
            $response['balance'] = 0;
            $response['fiat_value'] = 0;
        }
        return $response;
        // $this->output->set_content_type('application/json')->set_output(json_encode($response)); //test
    }
}
