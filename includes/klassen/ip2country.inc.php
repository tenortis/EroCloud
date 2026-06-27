<?php

/**
 *
 * DB-IP.com database query and management class
 *
 * Copyright (C) 2012 db-ip.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

class IP2Country {

    public $mysql;
    
    public function __construct($mysql) {
        $this->mysql = $mysql;
    }

	public function Lookup($addr) {

		if ($ret = $this->Do_Lookup(self::Addr_Type($addr), inet_pton($addr))) {
			$ret->ip_start = inet_ntop($ret->ip_start);
			$ret->ip_end = inet_ntop($ret->ip_end);
			return $ret;
		} else {
            return 'address not found';
			#throw new DBIP_Exception("address not found");
		}

	}

	protected function Do_Lookup($addr_type, $addr_start) {

        $rs_country = $this->mysql->query("SELECT * FROM `ip_country` WHERE `addr_type`='".p4c_escape_string($addr_type)."' AND `ip_start`<='".p4c_escape_string($addr_start)."' ORDER BY `ip_start` DESC LIMIT 1;", __FILE__, __LINE__);
        return p4c_fetch_object($rs_country);

		#$q = $this->db->prepare("select * from `{$table_name}` where addr_type = ? and ip_start <= ? order by ip_start desc limit 1");
		#$q->execute(array($addr_type, $addr_start));
		#return $q->fetchObject();
	}
	
	static private function Addr_Type($addr) {

		if (ip2long($addr) !== false) {
			return "ipv4";
		} else if (preg_match('/^[0-9a-fA-F:]+$/', $addr) && @inet_pton($addr)) {
			return "ipv6";
		} else {
			#throw new DBIP_Exception("unknown address type for {$addr}");
            return "unknown address type for {$addr}";
		}

	}

}



?>
