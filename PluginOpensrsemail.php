<?php

require_once 'lib/OpenSRSEmail.php';

class PluginOpensrsemail extends ServerPlugin
{
    public $features = array(
        'packageName' => false,
        'testConnection' => true,
        'showNameservers' => false,
        'directlink' => false,
        'upgrades' => false,
        'publicPanels' => [
            'emails' => 'Email Accounts'
        ]
    );

    public $icons = [
        'emails' => 'fa fa-envelope',
    ];

    private $api = null;

    public function getVariables()
    {
        $variables = [
            lang("Name") => [
                "type" => "hidden",
                "description" => "Used By CE to show plugin - must match how you call the action function names",
                "value" => "OpenSRS Email"
            ],
            lang("Description") => [
                "type" => "hidden",
                "description" => lang("Description viewable by admin in server settings"),
                "value" => lang("OpenSRS Email Integration")
            ],
            lang("Username") => [
                "type" => "text",
                "description" => lang("Admin Username"),
                "value" => "",
                "encryptable" => false
            ],
            lang("Password") => [
                "type" => "password",
                "description" => lang("Admin Password"),
                "value" => "",
                "encryptable" => true
            ],
            lang("Cluster") => [
                "type" => "text",
                "description" => lang("Enter the cluster associated with your account (A, B, Test)"),
                "value" => "",
            ],
            lang("Email Domain Custom Field") => array(
                "type"        => "text",
                "description" => lang("Enter the name of the package custom field that will hold email domain"),
                "value"       => ""
            ),
            lang("Actions") => [
                "type" => "hidden",
                "description" => lang("Current actions that are active for this plugin per server"),
                "value" => "Create,Delete,Suspend,UnSuspend"
            ],
            lang('reseller')  => [
                'type'          => 'hidden',
                'description'   => lang('Whether this server plugin can set reseller accounts'),
                'value'         => '0',
            ]
        ];

        return $variables;
    }

    private function setup($args)
    {
        $this->api = new OpenSRSEmail(
            $args['server']['variables']['plugin_opensrsemail_Username'],
            $args['server']['variables']['plugin_opensrsemail_Password'],
            $args['server']['variables']['plugin_opensrsemail_Cluster']
        );
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->create($args);
        return $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE) . ' has been created.';
    }

    public function create($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);
        $domain = $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $response = $this->api->createDomain($domain);
        if ($response['is_success'] == 0) {
            throw new CE_Exception($response['response_text']);
        }
    }

    public function doUpdate($args)
    {
    }

    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->delete($args);
        return $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE) . ' has been deleted.';
    }

    public function delete($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);
        $domain = $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $response = $this->api->deleteDomain($domain);
        if ($response['is_success'] == 0) {
            throw new CE_Exception($response['response_text']);
        }
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->suspend($args);
        return $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE) . ' has been suspended.';
    }

    public function suspend($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);
        $domain = $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $response = $this->api->disableDomain($domain);
        if ($response['is_success'] == 0) {
            throw new CE_Exception($response['response_text']);
        }
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->unsuspend($args);
        return $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE) . ' has been unsuspended.';
    }

    public function unsuspend($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);
        $domain = $userPackage->getCustomField($args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $response = $this->api->enableDomain($domain);
        if ($response['is_success'] == 0) {
            throw new CE_Exception($response['response_text']);
        }
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to OpenSRS Email');
        $this->setup($args);

        $username = $args['server']['variables']['plugin_opensrsemail_Username'];
        if (($pos = strpos($username, '@')) !== false) {
            $domain = substr($username, $pos + 1);
        } else {
            throw new CE_Exception('Invalid username.');
        }

        $result = $this->api->getDomain($domain);
        if ($result['is_success'] == 0) {
            throw new CE_Exception($result['response_text']);
        }
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $result = $this->api->getDomain(
            $userPackage->getCustomField(
                $args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'],
                CUSTOM_FIELDS_FOR_PACKAGE
            )
        );

        if ($result['is_success'] == 0 && $result['response_code'] == 2) {
            $actions[] = 'Create';
        } else {
            if ($result['response']['attributes']->disabled) {
                $actions[] = 'UnSuspend';
            } else {
                $actions[] = 'Suspend';
            }
            $actions[] = 'Delete';
        }
        return $actions;
    }

    public function emails($userPackage, $view)
    {
        $view->addScriptPath(APPLICATION_PATH . '/../plugins/server/opensrsemail/');
        $view->userPackageId = $userPackage->id;

        $args = $this->buildParams($userPackage);

        try {
            $this->setup($args);
            $emails = $this->api->getDomainMailboxes(
                $userPackage->getCustomField(
                    $args['server']['variables']['plugin_opensrsemail_Email_Domain_Custom_Field'],
                    CUSTOM_FIELDS_FOR_PACKAGE
                )
            );
            $view->emails = $emails['response']['users'];
        } catch (Exception $e) {
            $view->emails = [];
        }
        return $view->render('emails.phtml');
    }

    public function addMailbox($request)
    {
        $userPackage = new UserPackage($request['id']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $response = $this->api->createMailbox($request['email'], $request['password']);
        if ($response['is_success'] == 1) {
            return ['message' => "{$request['email']} successfully created"];
        }
        throw new CE_Exception($response['response_text']);
    }

    public function deleteMailbox($request)
    {
        $userPackage = new UserPackage($request['id']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $response = $this->api->deleteMailbox($request['email']);
        if ($response['is_success'] == 1) {
            return ['message' => "{$request['email']} successfully deleted"];
        }
        throw new CE_Exception($response['response_text']);
    }

    public function getToken($request)
    {
        $userPackage = new UserPackage($request['id']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $mailbox = $request['email'];

        $response = $this->api->getToken($mailbox);
        if ($response['is_success'] == 1) {
            $token = $response['response']['token'];
            return [
                'data' => [
                    'url' => "https://mail.{$args['server']['variables']['plugin_opensrsemail_Cluster']}.hostedemail.com/mail?user={$mailbox}&pass={$token}"
                ],
                'message' => '',
            ];
        }
        throw new CE_Exception($response['response_text']);
    }
}
