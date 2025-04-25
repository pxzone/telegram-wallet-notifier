<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Wallet_notifier_bot_model extends CI_Model {

    public function registerWalletNotifierData($chat_id, $message_text)
    {
        $data = array(
            'chat_id'=>$chat_id, 
            'coin'=>'btc', 
            'wallet_address'=>$message_text
        ); 
        $this->db->INSERT('telegram_wallet_address_notifier_tbl', $data);
        return $this->db->insert_id();
    }
    public function updateWalletAddressList($wallet_id, $data)
    {
        $data_arr = array(
            'txn_count'=>$data['txn_count'],
            'last_txid'=>$data['txid']
        ); 
        $this->db->WHERE('id', $wallet_id)->UPDATE('telegram_wallet_address_notifier_tbl', $data_arr);
    }
    public function getWalletAddress($chat_id)
    {
        return $this->db->SELECT('id, wallet_address, label, btc_balance, coin')
            ->WHERE('chat_id', $chat_id)
            ->ORDER_BY('id', 'desc')
            ->GET('telegram_wallet_address_notifier_tbl')->result_array();
    }
    public function checkAddress($chat_id, $message_text)
    {
        return $this->db->WHERE('chat_id', $chat_id)
            ->WHERE('wallet_address', $message_text)
            ->GET('telegram_wallet_address_notifier_tbl')
            ->num_rows();
    }
    public function insertSystemActivityLog ($message) 
    {
        $msg_log = array(
            'msg_log'=>$message, 
            'ip_address'=>$this->input->ip_address(), 
            'created_at'=>date('Y-m-d H:i:s')
        ); 
        $this->db->INSERT('altt_syslog_tbl', $msg_log);
    }
    public function getWalletDataByID($id)
    {
        return $this->db->SELECT('wallet_address, label, btc_balance, fiat_value, coin')
            ->WHERE('id', $id)
            ->GET('telegram_wallet_address_notifier_tbl')->row_array();
    }
    public function deleteWalletAddress($id)
    {
        $this->db->WHERE('id', $id)->DELETE('telegram_wallet_address_notifier_tbl');

        // delete logs
        $this->db->WHERE('wallet_id', $id)->DELETE('wallet_address_notification_log_tbl');
    }
    public function insertWalletAddressLog($wallet_id, $txid)
    {
        $data_arr = array(
            'wallet_id' => $wallet_id,
            'txid' => $txid
        );
        $this->db->INSERT('wallet_address_notification_log_tbl', $data_arr);
    }
    public function getAllWalletAddressLimit($limit, $start)
    {
        return $this->db->SELECT('id, chat_id, label, wallet_address, coin, txn_count, last_txid')
            ->WHERE('status', 'active')
            ->LIMIT($limit, $start)
            ->GET('telegram_wallet_address_notifier_tbl')->result_array();
    }
    public function getAllWalletAddress()
    {
        return $this->db->SELECT('id, chat_id, label, wallet_address, coin, txn_count, last_txid')
            ->WHERE('status', 'active')
            ->ORDER_BY('id', 'desc')
            ->GET('telegram_wallet_address_notifier_tbl')->result_array();
    }
    public function addNewTransactionLog($wallet_id, $txid)
    {
        $data = array(
            'wallet_id'=>$wallet_id,
            'txid'=>$txid,
        );
        $this->db->INSERT('wallet_address_notification_log_tbl', $data);
    }
    public function updateWalletAddressData($wallet_id, $txid, $txn_count)
    {
        $data_arr = array(
            'txn_count'=>$txn_count,
            'last_txid'=>$txid
        ); 
        $this->db->WHERE('id', $wallet_id)->UPDATE('telegram_wallet_address_notifier_tbl', $data_arr);
    }
    public function getOldTxids($wallet_id)
    {
        return $this->db->SELECT('txid')
            ->WHERE('wallet_id', $wallet_id)
            ->GET('wallet_address_notification_log_tbl')->result_array();
    }
    public function insertUserState ($chat_id, $state) 
    {
        $user_state = array(
            'chat_id'=>$chat_id, 
            'state'=>$state, 
        ); 
        $this->db->INSERT('wallet_address_userstate_tbl', $user_state);
    }
    public function getUserState ($chat_id) 
    {
        $query = $this->db->SELECT('state')
            ->LIMIT(1)
            ->ORDER_BY('id', 'desc')
            ->WHERE('chat_id', $chat_id)
            ->GET('wallet_address_userstate_tbl')->row_array();
        return $query['state'];
    }
    
    public function deleteUserState ($chat_id) 
    {
        $this->db->WHERE('chat_id', $chat_id)->DELETE('wallet_address_userstate_tbl');
    }
    public function insertWalletLabel ($wallet_id, $label) 
    {
        $data = array(
            'label'=>$label, 
        ); 
        $this->db->WHERE('id', $wallet_id)->UPDATE('telegram_wallet_address_notifier_tbl', $data);
    }
    public function getUserSettings ($chat_id) 
    {
        $query = $this->db->SELECT('btc_explorer as explorer_id, name as explorer_name, domain as explorer_url, address_url, txid_url')
            ->FROM('telegram_wallet_users_tbl as twt')
            ->JOIN('wallet_address_explorer_tbl as wat', 'wat.id=twt.btc_explorer', 'LEFT')
            ->WHERE('chat_id', $chat_id)
            ->GET();
        
       if($query->num_rows() > 0)
       {
            return $query->row_array();
       }
    }
    public function registerUser ($chat_id) 
    {
        $query = $this->db->WHERE('chat_id', $chat_id)
            ->GET('telegram_wallet_users_tbl')->num_rows();
            
        if($query <= 0)
        {
            $data_arr = array(
                'chat_id'=>$chat_id,
                'created_at'=>date('Y-m-d H:i:s')
            );
            $this->db->INSERT('telegram_wallet_users_tbl', $data_arr);
        }
    }
    public function getWalletExplorer () 
    {
        return $this->db->SELECT('id, name')
            ->GET('wallet_address_explorer_tbl')->result_array();
    }
    public function updateUserExplorer ($explorer_id, $chat_id) 
    {
        $data_arr = array(
            'btc_explorer'=>$explorer_id
        );
         $this->db->WHERE('chat_id', $chat_id)->UPDATE('telegram_wallet_users_tbl', $data_arr);
    }
    public function getActiveUserCount () 
    {
        $query = $this->db->SELECT('COUNT(DISTINCT tw.chat_id) AS active_count')
            ->FROM('telegram_wallet_users_tbl as tw')
            ->JOIN('telegram_wallet_address_notifier_tbl as twa', 'tw.chat_id=twa.chat_id', 'INNER')
            ->GET();
        $result = $query->row_array();
        return $result['active_count'];
    }
   
    public function checkTxidExists ($txid, $wallet_id) 
    {
        return $this->db->WHERE('txid', $txid)
            ->WHERE('wallet_id', $wallet_id)
            ->GET('wallet_address_notification_log_tbl')->num_rows();
    }
    public function insertWalletBalance($wallet_address, $crypto_value, $fiat_value)
    {   
        $data_arr = array(
            'btc_balance' => $crypto_value,
            'fiat_value' => $fiat_value,
        );
        $this->db->WHERE('wallet_address', $wallet_address)->UPDATE('telegram_wallet_address_notifier_tbl', $data_arr);
    }
    public function getAllWalletAddressCount()
    {
        return $this->db->WHERE('status', 'active')
            ->GET('telegram_wallet_address_notifier_tbl')->num_rows();
    }
    public function insertWalletNotifierTgLogs($chat_id, $wallet_id, $type, $txid){
        $msg_log = array(
            'chat_id'=>$chat_id, 
            'wallet_id'=>$wallet_id, 
            'type'=>strtolower($type), 
            'txid'=>$txid, 
            'created_at'=>date('Y-m-d H:i:s')
        ); 
        $this->db->INSERT('telegram_wallet_address_notifier_log_tbl', $msg_log);
    }
}


