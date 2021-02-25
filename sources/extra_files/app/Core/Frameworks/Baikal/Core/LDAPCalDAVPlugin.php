<?php

namespace Baikal\Core;

use Sabre\DAV;
use Sabre\DAVACL;
use Sabre\DAV\Xml\Property\LocalHref;
use Symfony\Component\Yaml\Yaml;

class LDAPCalDAVPlugin extends \Sabre\CalDAV\Plugin
{
    private $config;
   
    public function initialize(DAV\Server $server) {
        parent::initialize($server);
        $this->config = Yaml::parseFile(PROJECT_PATH_CONFIG . "baikal.yaml");
    }
    
    
    public function propFind($propFind, DAV\INode $node) {
        parent::propFind($propFind, $node);
        if ($node instanceof DAVACL\IPrincipal) {
            $addresses = $node->getAlternateUriSet();
            $addresses = array_merge($addresses, $this->getMailAliases($addresses[0]));
            $addresses[] = $this->server->getBaseUri() . $node->getPrincipalUrl() . '/';
            $lHrefs = new \Sabre\DAV\Xml\Property\LocalHref($addresses);
            $propFind->set('{' . self::NS_CALDAV . '}calendar-user-address-set', $lHrefs);
        }
    }

    private function getMailAliases($userEmail) {
        if (!isset($userEmail))
            return [];

        if (strpos($userEmail, 'mailto:') === 0) {
            $userEmail = substr($userEmail, 7);
        }

        $conn = ldap_connect($this->config['system']['dav_ldap_uri']);
        if (!$conn)
            return [];
        if (!ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3))
            return [];
        
        $arr = explode('@', $userEmail, 2);
        $dn = str_replace('%n', $userEmail, $this->config['system']['dav_ldap_dn_template']);
        $dn = str_replace('%u', $arr[0], $dn);
        if (isset($arr[1]))
            $dn = str_replace('%d', $arr[1], $dn);

        $conf_ldap_mail = $this->config['system']['dav_ldap_email_attr'];
        $result = ldap_search($conn, $dn, "(cn=*)", [$conf_ldap_mail]);
        $data = ldap_get_entries($conn, $result);

        if (!isset($data[0]) || !isset($data[0][$conf_ldap_mail]))
            return [];

        $addresses = [];
        foreach($data[0][$conf_ldap_mail] as $k => $data_mail) {
            if ($k !== 'count' && $data_mail !== $userEmail)
                $addresses[] = 'mailto:' . $data_mail;
        }
        return $addresses;
    }
}
