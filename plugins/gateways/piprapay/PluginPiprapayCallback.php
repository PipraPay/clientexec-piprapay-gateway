<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice.php';

class PluginpiprapayCallback extends PluginCallback
{
    function processCallback()
    {
        $cPlugin = new Plugin('', 'piprapay', $this->user);
        $api_key = trim($cPlugin->GetPluginVariable("plugin_piprapay_API Key"));
        $base_url = trim($cPlugin->GetPluginVariable("plugin_piprapay_Base URL"));

        // Step 1: Read incoming webhook data
        $rawData = file_get_contents("php://input");
        
        if (empty($rawData)) {
            $returnURL = CE_Lib::getSoftwareURL() . "/index.php?fuse=billing&controller=invoice&view=allinvoices&filter=open";
            header("Location: " . $returnURL);
            exit;
        }

        $headers = getallheaders();

        $received_api_key = '';
        
        if (isset($headers['mh-piprapay-api-key'])) {
            $received_api_key = $headers['mh-piprapay-api-key'];
        } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
            $received_api_key = $headers['Mh-Piprapay-Api-Key'];
        } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
            $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
        }
        
        if ($received_api_key !== $api_key) {
            status_header(401);
            echo json_encode(["status" => false, "message" => "Unauthorized request."]);
            exit;
        }

        $data = json_decode($rawData, true);

        // Step 3: Check pp_id exists
        if (!isset($data['pp_id'])) {
            http_response_code(400);
            echo json_encode(["status" => false, "message" => "Missing pp_id."]);
            exit;
        }
        
        $pp_id = $data['pp_id'];
        
        // Step 4: Call PipraPay Verify API
        $verify_url = $base_url . '/api/verify-payments';

        $payload = json_encode(["pp_id" => $pp_id]);
        
        $ch = curl_init($verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        
        $verifyData = json_decode($response, true);
        
        // Optional: log $verifyData for debugging
        $invoiceId = $data['metadata']['invoiceid'] ?? null;
        
        $price = $verifyData['amount'] . " " . $verifyData['currency'];
    
        if ($verifyData['status'] == 'completed') {
            $cPlugin = new Plugin($invoiceId, 'piprapay', $this->user);
            $cPlugin->setAmount($verifyData['amount']);
            $cPlugin->setAction('charge');
    
            $transaction = $verifyData['payment_method']." payment of $price Successful (Order ID: " . $invoiceId . ")";
            // Create plug in class to interact with CE
            if ($cPlugin->IsUnpaid() == 1) {
                $cPlugin->PaymentAccepted($amount, $transaction);
                $returnURL = CE_Lib::getSoftwareURL() . "/index.php?fuse=billing&paid=1&controller=invoice&view=invoice&id=" . $invoiceId;
                header("Location: " . $returnURL);
                exit;
            } else {
                return;
            }
        }else{
            $transaction = $verifyData['payment_method']." payment of $price Failed (Order ID: " . $invoiceId . ")";
            $cPlugin->PaymentRejected($transaction);
        }
        return;
    }
}