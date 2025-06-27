<?php
require_once 'modules/admin/models/GatewayPlugin.php';
class Pluginpiprapay extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array(
            lang("Plugin Name") => array(
                "type"          => "hidden",
                "description"   => "",
                "value"         => "PipraPay"
            ),
            lang('Signup Name') => array(
                'type'        => 'text',
                'description' => lang('Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card.'),
                'value'       => 'PipraPay'
            ),
            lang("API Key") => array(
                "type"          => "text",
                "description"   => "Enter your api key here",
                "value"         => ""
            ),
            lang("Base URL") => array(
                "type"          => "text",
                "description"   => "Enter your payment panel base url",
                "value"         => ""
            ),
            lang("Currency") => array(
                "type"          => "text",
                "description"   => "Enter your currency",
                "value"         => "BDT"
            ),
        );
        return $variables;
    }
    function singlepayment($params)
    {
        $invoiceId = $params['invoiceNumber'];
        $description = $params['invoiceDescription'];
        $amount = sprintf("%01.2f", round($params["invoiceTotal"], 2));
        $systemUrl = $params['companyURL'];
        $firstname = $params['userFirstName'];
        $lastname = $params['userLastName'];
        $email = $params['userEmail'];
        
        $baseURL = rtrim(CE_Lib::getSoftwareURL(), '/') . '/';
        $callbackURL = $baseURL . "plugins/gateways/piprapay/callback.php";
        $cancelURL = $params['invoiceviewURLCancel'];
        
        $api_key = $params['plugin_piprapay_API Key'];
        $base_url = $params['plugin_piprapay_Base URL'];
        $currency = $params['plugin_piprapay_Currency'];
        
        $url = $base_url . '/api/create-charge';
        
        $data = [
            'full_name' => $firstname . ' ' . $lastname,
            'email_mobile' => $email,
            'amount' => $amount,
            'metadata' => [
                'invoiceid' => $invoiceId
            ],
            'redirect_url' => $callbackURL,
            'return_type' => 'GET',
            'cancel_url' => $cancelURL,
            'webhook_url' => $callbackURL,
            'currency' => $currency
        ];
        
        
        
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: '.$api_key
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Force TLS 1.2
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        
        // Optional (debug)
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        $response = curl_exec($ch);
        
        
        
        if ($response === false) {
            die("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        
        $urlData = json_decode($response, true);
        
        if (isset($urlData['status']) && $urlData['status'] === true && isset($urlData['pp_url'])) {
            header('Location: ' . $urlData['pp_url']);
            exit();
        } else {
            die("Initialization Error: " . $response);
        }
    }
    function credit($params)
    {
    }
}