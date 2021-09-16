<?php

/*
 * This file is part of the HAISTAR Core Integration.
 *
 * (c) Nanda Firmansyah <nafima21@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Haistar\Integration;

use PDO;

class Stock
{
    
    private $db_slave;
    private $db_helios;

    public function __construct(PDO $db_slave, PDO $db_helios)
    {
        $this->db_slave  = $db_slave;
        $this->db_helios = $db_helios;
    }

    public function withQueue($apikey, $item_code, $location_code, $stock_allocation)
    {
        return $apikey;
    }

    public function withoutQueue($apikey, $item_code, $location_code, $stock_allocation)
    {
        return $apikey;
    }
}