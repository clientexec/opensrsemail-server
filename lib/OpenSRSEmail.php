<?php

class OpenSRSEmail
{

    protected $username;
    protected $password;
    protected $domain;
    protected $cluster;


    public function getDomain($domain)
    {
        $params = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'domain' => $domain
        ];

        $result = $this->processRequest("get_domain", $params);
        return $result;
    }

    public function createDomain($domain)
    {

        $compile = [
            'attributes' => new ArrayObject(),
            'domain' => $domain,
            'create_only' => true,
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ]
        ];

        $result = $this->getDomain($domain);

        if (!$result['is_success']) {
            return $this->processRequest('change_domain', $compile);
        } else {
            $result = array(
                'is_success' => '0',
                'response_code' => '7',
                'response_text' => 'This domain already exists.'
            );
            return $result;
        }
    }

    public function disableDomain($domain)
    {
        $compile = [
            'attributes' => [
                'disabled' => true
            ],
            'domain' => $domain,
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ]
        ];

        return $this->processRequest('change_domain', $compile);
    }

    public function enableDomain($domain)
    {
        $compile = [
            'attributes' => [
                'disabled' => false
            ],
            'domain' => $domain,
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ]
        ];

        return $this->processRequest('change_domain', $compile);
    }

    public function deleteDomain($domain)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'domain' => $domain
        ];
        return $this->processRequest('delete_domain', $compile);
    }

    public function getDomainMailboxes($domain)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'criteria' => ['domain' => $domain]
        ];
        $response = $this->processRequest('search_users', $compile);

        return $response;
    }

    public function getMailbox($mailbox)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'user' => $mailbox
        ];
        $response = $this->processRequest('get_user', $compile);

        return $response;
    }

    public function createMailbox($mailbox, $password)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'create_only' => true,
            'user' => $mailbox,
            'attributes' => [
                'password' => $password,
            ]
        ];
        return $this->processRequest('change_user', $compile);
    }

    public function deleteMailbox($mailbox)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'user' => $mailbox
        ];
        return $this->processRequest('delete_user', $compile);
    }

    public function getToken($mailbox)
    {
        $compile = [
            'credentials' => [
                'user' => $this->username,
                'password' => $this->password
            ],
            'user' => $mailbox,
            'reason' => 'Login to Webmail',
            'type' => 'session',
        ];
        $response = $this->processRequest('generate_token', $compile);

        return $response;
    }

    public function __construct($username, $password, $cluster)
    {
        $this->username = $username;
        $this->password = $password;

        if (($pos = strpos($username, '@')) !== false) {
            $domain = substr($username, $pos + 1);
            $this->domain   = $domain;
        }
        $this->cluster  = strtolower($cluster);
    }

    private function processRequest($method, $command = '')
    {
        $sequence = [
            0 => "ver ver=\"3.5\"",
            1 => "login user=\"" . $this->username . "\" domain=\"" . $this->domain . "\" password=\"" . $this->password . "\"",
            2 => $method,
            3 => $command,
            4 => "quit"
        ];

        $response = $this->makeCall($sequence);
        return $this->parseResult($response);
    }

    protected function makeCall($sequence)
    {
        $result = '';
        $url = 'https://admin.' . $this->cluster . '.hostedemail.com/api/' . $sequence[2];

        $dataString = json_encode($sequence[3]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ','Content-Type: application/json','Content-Length: ' . strlen($dataString)));
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }

        $response = curl_exec($ch);
        $getInfo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($getInfo >= '200' && $getInfo <= '206') {
            $result = $response;
        } else {
            throw new Exception("Error connecting to OpenSRS");
        }
        return $result;
    }

    protected function parseResult($resString)
    {
        $resArray = (array) json_decode($resString);
        $result = [
            'is_success' => '1',
            'response_code' => '200',
            'response_text' => 'Command completed successfully',
            'response' => $resArray
        ];

        if ($resArray['success'] == '0' && isset($resArray['success'])) {
            $result['response_text'] = $resArray['error'];
            $result['response_code'] = $resArray['error_number'];
            $result['is_success'] = '0';
        }

        return $result;
    }
}
