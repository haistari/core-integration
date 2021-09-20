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

    public function withoutQueue($apikey, $item_code, $location_code, $allocation)
    {
        try 
        {
            $error   = "";
            $success = array();

            $stmt_client = $this->db_helios->prepare("SELECT program_id as client_id, name as client_name, code as client_code FROM program WHERE api_key = :apikey");
            $stmt_client->execute([":apikey" => $apikey]);
            $client = $stmt_client->fetch();
            if($stmt_client->rowCount() > 0)
            {
                $stmt_location = $this->db_helios->prepare("SELECT location_id, name, code as location_code FROM location WHERE code = :location_code");
                $stmt_location->execute([":location_code" => $location_code]);
                $location = $stmt_location->fetch();
                if($stmt_location->rowCount() > 0)
                {
                    $stock_type = ($allocation == "MULTI CHANNEL") ? "NONE MARKET PLACE" : $allocation;
                    $stmt_stock_type = $this->db_helios->prepare("SELECT market_place_id FROM marketplace WHERE name = :stock_type");
                    $stmt_stock_type->execute([":stock_type" => $stock_type]);
                    $stock_allocation = $stmt_stock_type->fetch();
                    if($stmt_stock_type->rowCount() > 0)
                    {
                        $stmt_client2 = $this->db_slave->prepare("SELECT client_id, code FROM client WHERE code = :client_code");
                        $stmt_client2->execute([":client_code" => $client['client_code']]);
                        $client2 = $stmt_client2->fetch();
                        if($stmt_client2->rowCount() > 0)
                        {
                            $stmt_location2 = $this->db_slave->prepare("SELECT location_id FROM location WHERE code = :location_code");
                            $stmt_location2->execute([":location_code" => $location['location_code']]);
                            if($stmt_location2->rowCount() > 0)
                            {
                                $stmt_check_bundling = $this->db_slave->prepare("SELECT d.name as item_managed, a.item_id, a.code as item_code, a.name as item_name, a.barcode as item_barcode, a.bundling_kitting, a.bundling_dynamic, a.publish_price FROM item a LEFT JOIN itemmanaged d ON d.item_managed_id = a.item_managed_id WHERE a.client_id = :client_id AND a.code = :item_code");
                                $stmt_check_bundling->execute([":client_id" => $client2['client_id'], ":item_code" => $item_code]);
                                $check_item = $stmt_check_bundling->fetch();
                                if($stmt_check_bundling->rowCount() > 0)
                                {
                                    if($check_item['bundling_dynamic'] == 1)
                                    {
                                        $stmt_bundling = $this->db_slave->prepare("SELECT a.parent_id, a.child_id, a.quantity, b.code as item_code FROM bundling a LEFT JOIN item b ON a.child_id = b.item_id WHERE a.parent_id = :item_id");
                                        $stmt_bundling->execute([":item_id" => $check_item['item_id']]);
                                        $bundling = $stmt_bundling->fetchAll();
                                        if($stmt_bundling->rowCount() > 0)
                                        {
                                            $selection_exist = array();
                                            $selection_damage = array();
                                            foreach($bundling as $bd)
                                            {
                                                $stmt_item = $this->db_helios->prepare("SELECT a.program_item_id, a.name as item_name, a.code as item_code, upper(b.name) as item_managed, a.additional_expired FROM programitem a LEFT JOIN itemmanaged b ON a.item_managed_id = b.item_managed_id WHERE a.code = :item_code AND a.program_id = :client_id");
                                                $stmt_item->execute([":item_code" => $bd['item_code'], ":client_id" => $client['client_id']]);
                                                $item = $stmt_item->fetch();
                                                if($stmt_item->rowCount() > 0)
                                                {
                                                    if($item['item_managed'] == "EXPIRED DATE")
                                                    {
                                                        $expdt = date('Y-m-d', strtotime('+'.$item['additional_expired'].' day', time()));
                                                        $sql_inventory = "SELECT 
                                                            (
                                                                SELECT SUM(id.exist_quantity) 
                                                                FROM inventorydetail id
                                                                JOIN grid grs ON id.grid_id = grs.grid_id
                                                                JOIN gridarea gras ON gras.grid_area_id = grs.grid_area_id
                                                                WHERE id.inventory_id = a.inventory_id 
                                                                AND id.is_damaged = 0 
                                                                AND id.exist_quantity > 0 
                                                                AND gras.code <> 'GAREA009'
                                                                AND id.item_information >= '".$expdt."') as exist_quantity,
                                                            (
                                                                SELECT SUM(id.exist_quantity) 
                                                                FROM inventorydetail id 
                                                                JOIN grid grs ON id.grid_id = grs.grid_id
                                                                JOIN gridarea gras ON gras.grid_area_id = grs.grid_area_id
                                                                WHERE id.inventory_id = a.inventory_id 
                                                                AND id.is_damaged = 1 
                                                                AND gras.code <> 'GAREA009'
                                                                AND id.exist_quantity > 0) as damaged_quantity
                                                        FROM inventory a 
                                                        WHERE a.location_id = ? AND a.market_place_id = ? AND a.program_item_id = ?";
                                                    }
                                                    else
                                                    {
                                                        $sql_inventory = "SELECT 
                                                            (
                                                                SELECT SUM(id.exist_quantity) 
                                                                FROM inventorydetail id
                                                                JOIN grid grs ON id.grid_id = grs.grid_id
                                                                JOIN gridarea gras ON gras.grid_area_id = grs.grid_area_id
                                                                WHERE id.inventory_id = a.inventory_id 
                                                                AND id.is_damaged = 0 
                                                                AND gras.code <> 'GAREA009'
                                                                AND id.exist_quantity > 0) as exist_quantity,
                                                            (
                                                                SELECT SUM(exist_quantity) 
                                                                FROM inventorydetail id
                                                                JOIN grid grs ON id.grid_id = grs.grid_id
                                                                JOIN gridarea gras ON gras.grid_area_id = grs.grid_area_id
                                                                WHERE inventory_id = a.inventory_id 
                                                                AND is_damaged = 1 
                                                                AND gras.code <> 'GAREA009'
                                                                AND exist_quantity > 0) as damaged_quantity
                                                        FROM inventory a 
                                                        WHERE a.location_id = ? AND a.market_place_id = ? AND a.program_item_id = ?";
                                                    }
                                                    $stmt_inventory = $this->db_helios->prepare($sql_inventory);
                                                    $stmt_inventory->execute([$location['location_id'], $stock_allocation['market_place_id'], $item['program_item_id']]);
                                                    $inventory = $stmt_inventory->fetch();
                                                    if($stmt_inventory->rowCount() > 0)
                                                    {
                                                        $exist_quantity = floor(intval($inventory['exist_quantity']) / intval($bd['quantity']));
                                                        $exist_quantity = ($exist_quantity < 0) ? 0 : $exist_quantity;
 
                                                        $selection_exist[] = intval($exist_quantity);

                                                        $damaged_quantity = floor(intval($inventory['damaged_quantity']) / intval($bd['quantity']));
                                                        $damaged_quantity = ($damaged_quantity < 0) ? 0 : $damaged_quantity;
 
                                                        $selection_damage[] = intval($damaged_quantity);
                                                    }
                                                    else
                                                    {
                                                        $selection_exist[] = intval(0);
                                                        $selection_damage[] = intval(0);
                                                    }
                                                }
                                                else
                                                {
                                                    $error .= "ITEM ".str_replace("'", "", $bd['item_code'])." NOT FOUND AT HELIOS, ";
                                                }
                                            }

                                            $success = array(
                                                "client_code"      => $client2['code'],
                                                "location_code"    => $location['location_code'],
                                                "stock_allocation" => strtoupper($stock_type),
                                                "item_code"        => $check_item['item_code'],
                                                "item_managed"     => $check_item['item_managed'],
                                                "item_name"        => mb_convert_encoding($check_item['item_name'], "UTF-8", "HTML-ENTITIES"),
                                                "item_barcode"     => $check_item['item_barcode'],
                                                "item_price"       => floatval($check_item['publish_price']),
                                                "exist_quantity"   => intval(min($selection_exist)),
                                                "damaged_quantity" => intval(min($selection_damage))
                                            );
                                        }
                                        else
                                        {
                                            $error .= "BUNDLING ".str_replace("'", "", $item_code)." NOT FOUND, "; 
                                        }
                                    }
                                    else
                                    {
                                        $stmt_item = $this->db_helios->prepare("SELECT a.program_item_id, a.name as item_name, a.barcode as item_barcode, a.code as item_code, upper(b.name) as item_managed, a.additional_expired, a.publish_price FROM programitem a LEFT JOIN itemmanaged b ON a.item_managed_id = b.item_managed_id WHERE a.code = :item_code AND a.program_id = :client_id");
                                        $stmt_item->execute([":item_code" => $item_code, ":client_id" => $client['client_id']]);
                                        $item = $stmt_item->fetch();
                                        if($stmt_item->rowCount() > 0)
                                        {
                                            if($item['item_managed'] == "EXPIRED DATE")
                                            {
                                                $expdt = date('Y-m-d', strtotime('+'.$item['additional_expired'].' day', time()));
                                                $sql_inventory = "SELECT 
                                                    (SELECT SUM(inv.exist_quantity) FROM inventorydetail inv JOIN grid g ON inv.grid_id = g.grid_id JOIN gridarea ga ON g.grid_area_id = ga.grid_area_id WHERE inv.inventory_id = a.inventory_id AND inv.is_damaged = 0 AND inv.exist_quantity > 0 AND ga.code <> 'GAREA009' AND inv.item_information >= '".$expdt."') AS exist_quantity,
                                                    (SELECT SUM(inv.exist_quantity) FROM inventorydetail inv JOIN grid g ON inv.grid_id = g.grid_id JOIN gridarea ga ON g.grid_area_id = ga.grid_area_id WHERE inv.inventory_id = a.inventory_id AND inv.is_damaged = 1 AND inv.exist_quantity > 0 AND ga.code <> 'GAREA009') AS damaged_quantity
                                                FROM inventory a
                                                WHERE a.location_id = ? AND a.market_place_id = ? AND a.program_item_id = ?";
                                            }
                                            else
                                            {
                                                $sql_inventory = "SELECT 
                                                    (SELECT SUM(inv.exist_quantity) FROM inventorydetail inv JOIN grid g ON inv.grid_id = g.grid_id JOIN gridarea ga ON g.grid_area_id = ga.grid_area_id WHERE inv.inventory_id = a.inventory_id AND inv.is_damaged = 0 AND inv.exist_quantity > 0 AND ga.code <> 'GAREA009') AS exist_quantity,
                                                    (SELECT SUM(inv.exist_quantity) FROM inventorydetail inv JOIN grid g ON inv.grid_id = g.grid_id JOIN gridarea ga ON g.grid_area_id = ga.grid_area_id WHERE inv.inventory_id = a.inventory_id AND inv.is_damaged = 1 AND inv.exist_quantity > 0 AND ga.code <> 'GAREA009') AS damaged_quantity
                                                FROM inventory a 
                                                WHERE a.location_id = ? AND a.market_place_id = ? AND a.program_item_id = ?";
                                            }
                                            $stmt_inventory = $this->db_helios->prepare($sql_inventory);
                                            $stmt_inventory->execute([$location['location_id'], $stock_allocation['market_place_id'], $item['program_item_id']]);
                                            $inventory = mb_convert_encoding($stmt_inventory->fetch(), "UTF-8", "HTML-ENTITIES");
                                            if($stmt_inventory->rowCount() > 0)
                                            {
                                                $success = array(
                                                    "client_code"      => $client['client_code'],
                                                    "location_code"    => $location_code,
                                                    "stock_allocation" => $allocation,
                                                    "item_code"        => $item['item_code'],
                                                    "item_name"        => mb_convert_encoding($item['item_name'], "UTF-8", "HTML-ENTITIES"),
                                                    "item_barcode"     => $item['item_barcode'],
                                                    "item_price"       => floatval($item['publish_price']),
                                                    "exist_quantity"   => intval($inventory['exist_quantity']),
                                                    "damaged_quantity" => intval($inventory['damaged_quantity'])
                                                );
                                            }
                                            else
                                            {
                                                $error .= "INVENTORY NOT FOUND, ";
                                            }
                                        }
                                        else
                                        {
                                            $error .= "ITEM ".str_replace("'", "", $item_code)." NOT FOUND, ";
                                        }
                                    }
                                }
                                else
                                {
                                    $error .= "ITEM ".str_replace("'", "", $item_code)." NOT FOUND, ";
                                }
                            }
                            else
                            {
                                $error .= "LOCATION ".$location['location_code']." NOT FOUND AT EOS, ";
                            }
                        }
                        else
                        {
                            $error .= "CLIENT ".$client['client_code']." NOT FOUND AT EOS, ";
                        }
                    }
                    else
                    {
                        $error .= "STOCK TYPE ".$stock_type." NOT FOUND, ";
                    }
                }
                else
                {
                    $error .= "LOCATION CODE ".$location_code." NOT FOUND, ";
                }
            }
            else
            {
                $error .= "CLIENT APIKEY ".$apikey." NOT FOUND, ";
            }

            if(empty($error))
            {
                $msg = array(
                    "status"  => 200,
                    "message" => "GET STOCK WITHOUT QUEUE",
                    "data"    => $success
                );

                return $msg;
            }
            else
            {
                $msg = array(
                    "status"  => 204,
                    "message" => trim($error, ", "),
                    "data"    => null
                );

                return $msg;
            }
        } 
        catch (\PDOException $e) 
        {
            $msg = array(
                "status"  => 500,
                "message" => "SYSTEM FAILURE",
                "data"    => $e->getMessage()
            );

            return $msg;
        }
     }
}