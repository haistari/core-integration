<?php

/*
 * This file is part of the HAISTAR Core Integration.
 *
 * (c) Nanda Firmansyah <nafima21@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Haistar;

use PDO;
use Haistar\Integration\Stock;

class Connection
{
    private $StockConnection;
    private $OrderConnection;

    public function __construct(PDO $db_slave, PDO $db_master, PDO $db_helios)
    {
        $this->setStockConnection(new Stock($db_slave, $db_helios));
    }

    public function setStockConnection($StockConnection)
    {
        $this->StockConnection = $StockConnection;
        return $this;
    }

    public function getStockConnection()
    {
        return $this->StockConnection;
    }
}