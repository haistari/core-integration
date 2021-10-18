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
use Predis\Client;
use Haistar\Validator;

class Order
{
    private $db_slave;
    private $db_master;
    private $redis;

    public function __construct(PDO $db_slave, PDO $db_master, Client $redis)
    {
        $this->db_slave  = $db_slave;
        $this->db_master = $db_master;
        $this->redis     = $redis;
    }

    public function create(Array $order)
    {
        date_default_timezone_set('Asia/Jakarta');
        
        $valid = Validator::make($order, [
			"apikey"         => "required",
			"shop_name"      => "required",
			"payment_type"   => "required",
			"channel_name"   => "required",
			"stock_type"     => "required",
			"courier_name"   => "required",
			"delivery_type"  => "required",
			"order_type"     => "required",
			"location_code"  => "required",
			"order_code"     => "required",
			"ref_order_id"   => "required",
			"recipient"      => "required",
			"total_koli"     => "required",
			"price"          => "required",
			"stock_source"   => "required",
			"discount"       => "required",
			"order_date"     => "required",
			"payment_date"   => "required",
			"items"          => "required"
		]);

		if(!empty($valid))
		{
			return [
				"status"  => 400,
				"message" => "CANNOT PUSH ORDER BECAUSE: " . $valid,
				"data"    => null
			];
		}
		else
		{
			try  
			{
				$this->db_master->beginTransaction();
				
				$error = "";
				$total_weight = 0;
				$itemList = array();
				
				foreach ($order['items'] as $value) 
				{
					$total_weight += $total_weight + $value['item_weight'];
					$itemList[] = $value['sku'];
				}

				$redis = $this->redis;
		
                $stmt_client = $this->db_slave->prepare("SELECT client_id, name, code FROM client WHERE api_key = :apikey");
                $stmt_client->execute([":apikey" => $order['apikey']]);
                $client  = $stmt_client->fetch();
				if($stmt_client->rowCount() > 0)
				{
					retryPaymentType:
					if($redis->exists("paymenttype:".$order['payment_type']))
					{
						$paymenttype_id = $redis->get("paymenttype:".$order['payment_type']);
						retryChannel:
						if($redis->exists("channel:".$order['channel_name']))
						{
							$channel_id = $redis->get("channel:".$order['channel_name']);
							$shop_name  = $order['shop_name'];
							retryShopConfig:
							if($redis->exists("shopconfigID:".$client['client_id'].":".$channel_id.":".$shop_name))
							{
								$shopconfiguration_id = $redis->get("shopconfigID:".$client['client_id'].":".$channel_id.":".$shop_name);
								retryStockType:
								if($redis->exists("stocktype:".$client['client_id'].":".$order['stock_type']))
								{
									$stocktype_id = $redis->get("stocktype:".$client['client_id'].":".$order['stock_type']);
									retryCourier:
									if($redis->exists("courier:".$order['courier_name']))
									{
										$courier_id = $redis->get("courier:".$order['courier_name']);
										retryDeliveryType:
										if($redis->exists("deliverytype:".$order['delivery_type'].":".$courier_id))
										{
											$deliverytype_id = $redis->get("deliverytype:".$order['delivery_type'].":".$courier_id);
											retryOrderType:
											if($redis->exists("ordertype:".$order['order_type']))
											{
												$ordertype_id = $redis->get("ordertype:".$order['order_type']);
												retryLocation:
												if($redis->exists("location:".$order['location_code']))
												{
													$location_id = $redis->get("location:".$order['location_code']);
													retryStatus:
													if($redis->exists("status:ORD_UNFULFILLED"))
													{
														$status_id = $redis->get("status:ORD_UNFULFILLED");
														if(!$redis->exists("orderheader:".$order['order_code'].":".$client['client_id']))
														{
															$dropshipper_id = null;
															if(!empty($order['dropshipper']) && $order['dropshipper']['name'] != "")
															{
																$naming                 = $order['dropshipper']['name'].' ('.$order['dropshipper']['phone'].')';
																$sql_dropshipper_check  = "SELECT dropshipper_id FROM dropshipper WHERE client_id = ? AND name = ?";
																$stmt_dropshipper_check = $this->db_slave->prepare($sql_dropshipper_check);
                                                                $stmt_dropshipper_check->execute([$client['client_id'], $naming]);
                                                                $res_dropshipper_check = $stmt_dropshipper_check->fetch();
																if($stmt_dropshipper_check->rowCount() > 0)
																{
																	$dropshipper_id = $res_dropshipper_check['dropshipper_id'];
																}
																else
																{
                                                                    $drp              = $order['dropshipper']['name'].' ('.$order['dropshipper']['phone'].')';
                                                                    $drp_code         = 'DRP'.date('Ymd').rand(1000, 9999);
                                                                    $sql_dropshipper  = "INSERT INTO dropshipper(client_id, code, name, owner_name, phone, email, address, district, city, province, country, created_date, modified_date, created_by, modified_by) VALUES (?, ?, ?, ?, ?, NULL, '', '', '', '', '', NOW(), NOW(), 0, 0) RETURNING dropshipper_id";
                                                                    $stmt_dropshipper = $this->db_master->prepare($sql_dropshipper);
                                                                    $stmt_dropshipper->execute([$client['client_id'], $drp_code, $drp, $drp, $order['dropshipper']['phone']]);
                                                                    $drop           = $stmt_dropshipper->fetch();
                                                                    $dropshipper_id = $drop['dropshipper_id'];
																}
															}

															$data_order = array(
																":order_code"            => strtoupper($order['order_code']),
																":location_id"           => $location_id,
																":location_to"           => NULL,
																":client_id"             => $client['client_id'],
																":shop_configuration_id" => $shopconfiguration_id,
																":status_id"             => $status_id,
																":delivery_type_id"      => $deliverytype_id,
																":payment_type_id"       => $paymenttype_id,
																":distributor_id"        => NULL,
																":dropshipper_id"        => $dropshipper_id,
																":channel_id"            => $channel_id,
																":stock_type_id"         => $stocktype_id,
																":order_type_id"         => $ordertype_id,
																":ref_order_id"          => $order['ref_order_id'],
																":code"                  => strtoupper($order['order_code']),
																":order_date"            => $order['order_date'],
																":booking_number"        => $order['booking_number'],
																":waybill_number"        => $order['waybill_number'],
																":recipient_name"        => $order['recipient']['name'],
																":recipient_phone"       => $order['recipient']['phone'],
																":recipient_email"       => $order['recipient']['email'],
																":recipient_address"     => $order['recipient']['address'],
																":recipient_district"    => $order['recipient']['district'],
																":recipient_city"        => $order['recipient']['city'],
																":recipient_province"    => $order['recipient']['province'],
																":recipient_country"     => $order['recipient']['country'],
																":recipient_postal_code" => ($order['recipient']['postal_code'] == "" || $order['recipient']['postal_code'] == NULL) ? '00000' : $order['recipient']['postal_code'],
																":latitude"              => $order['latitude'],
																":longitude"             => $order['longitude'],
																":buyer_name"            => (isset($order['buyer']['name'])) ? $order['buyer']['name'] : null ,
																":buyer_phone"           => (isset($order['buyer']['phone'])) ? $order['buyer']['phone'] : null ,
																":buyer_email"           => (isset($order['buyer']['email'])) ? $order['buyer']['email'] : null ,
																":buyer_address"         => (isset($order['buyer']['address'])) ? $order['buyer']['address'] : null ,
																":buyer_district"        => (isset($order['buyer']['district'])) ? $order['buyer']['district'] : null ,
																":buyer_city"            => (isset($order['buyer']['city'])) ? $order['buyer']['city'] : null ,
																":buyer_province"        => (isset($order['buyer']['province'])) ? $order['buyer']['province'] : null ,
																":buyer_country"         => (isset($order['buyer']['country'])) ? $order['buyer']['country'] : null ,
																":buyer_postal_code"     => (isset($order['buyer']['postal_code'])) ? $order['buyer']['postal_code'] : null ,
																":total_koli"            => $order['total_koli'],
																":total_weight"          => $total_weight,
																":shipping_price"        => $order['price']['shipping'],
																":total_price"           => $order['price']['total_price'],
																":cod_price"             => $order['price']['cod'],
																":dfod_price"            => $order['price']['dfod'],
																":stock_source"          => $order['stock_source'],
																":notes"                 => $order['notes'],
																":remark"                => $order['remark'],
																":created_date"          => date("Y-m-d H:i:s", strtotime("now")),
																":modified_date"         => date("Y-m-d H:i:s", strtotime("now")),
																":created_by"            => 0,
																":modified_by"           => 0,
																":created_name"          => $order['created_name'],
																":shop_name"             => $order['shop_name'],
																":discount"              => (isset($order['discount']['product'])) ? intval($order['discount']['product']) : 0,
																":discount_shipping"     => (isset($order['discount']['shipping'])) ? intval($order['discount']['shipping']) : 0,
																":discount_point"        => (isset($order['discount']['point'])) ? intval($order['discount']['point']) : 0,
																":discount_seller"       => (isset($order['discount']['seller'])) ? intval($order['discount']['seller']) : 0,
																":discount_platform"     => (isset($order['discount']['platform'])) ? intval($order['discount']['platform']) : 0,
																":payment_date"          => $order['payment_date'],
																":total_product_price"	 => (isset($order['price']['product'])) ? intval($order['price']['total_price']) : 0,
																":fulfillment"			 => (isset($order['fulfillment'])) ? $order['fulfillment'] : null
															);
										
                                                            $stmt_orderheader = $this->db_master->prepare("INSERT INTO orderheader(order_code, location_id, location_to, client_id, shop_configuration_id, status_id, delivery_type_id, payment_type_id, distributor_id, dropshipper_id, channel_id, stock_type_id, order_type_id, ref_order_id, code, order_date, booking_number, waybill_number, recipient_name, recipient_phone, recipient_email, recipient_address, recipient_district, recipient_city, recipient_province, recipient_country, recipient_postal_code, latitude, longitude, buyer_name, buyer_phone, buyer_email, buyer_address, buyer_district, buyer_city, buyer_province, buyer_country, buyer_postal_code, total_koli, total_weight, shipping_price, total_price, cod_price, dfod_price, stock_source, notes, remark, created_date, modified_date, created_by, modified_by, created_name, store_name, discount, discount_shipping, discount_point, discount_seller, discount_platform, payment_date, total_product_price, fullfilmenttype_configuration_id) VALUES (:order_code, :location_id, :location_to, :client_id, :shop_configuration_id, :status_id, :delivery_type_id, :payment_type_id, :distributor_id, :dropshipper_id, :channel_id, :stock_type_id, :order_type_id, :ref_order_id, :code, :order_date, :booking_number, :waybill_number, :recipient_name, :recipient_phone, :recipient_email, :recipient_address, :recipient_district, :recipient_city, :recipient_province, :recipient_country, :recipient_postal_code, :latitude, :longitude, :buyer_name, :buyer_phone, :buyer_email, :buyer_address, :buyer_district, :buyer_city, :buyer_province, :buyer_country, :buyer_postal_code, :total_koli, :total_weight, :shipping_price, :total_price, :cod_price, :dfod_price, :stock_source, :notes, :remark, :created_date, :modified_date, :created_by, :modified_by, :created_name, :shop_name, :discount, :discount_shipping, :discount_point, :discount_seller, :discount_platform, :payment_date, :total_product_price, :fulfillment) RETURNING shop_configuration_id, client_id, ref_order_id, order_header_id, status_id, created_date, modified_date, created_by, modified_by");
                                                            $stmt_orderheader->execute($data_order);
                                                            $orderheader = $stmt_orderheader->fetch();
															
															if($stmt_orderheader->rowCount() > 0)
															{
                                                                $stmt_jobpushorder = $this->db_master->prepare("INSERT INTO jobpushorder(order_header_id, created_date) VALUES(:order_header_id, NOW())");
																$stmt_jobpushorder->execute([
                                                                    ":order_header_id" => $orderheader['order_header_id']
                                                                ]);
																if($stmt_jobpushorder->rowCount() == 0)
																{
																	$error .= "CANNOT CREATE JOBPUSHORDER WITH ORDER HEADER ID ".$orderheader['order_header_id'].", ";
																}
																else
																{                                                                    
                                                                    $stmt_createretry = $this->db_master->prepare("INSERT INTO tmpretry(channel_id, shop_configuration_id, order_header_id, order_code, acked, counter_ack, created_date, modified_date, created_by, modified_by) VALUES(:channel_id, :shop_configuration_id, :order_header_id, :ref_order_id, 0, 0, NOW(), NOW(), 0, 0)");
																	$stmt_createretry->execute([
                                                                        ":channel_id"            => $channel_id,
                                                                        ":shop_configuration_id" => $orderheader['shop_configuration_id'],
                                                                        ":order_header_id"       => $orderheader['order_header_id'],
                                                                        ":ref_order_id"          => $orderheader['ref_order_id']]);
																	if($stmt_createretry->rowCount() == 0)
																	{
																		$error .= "CANNOT CREATE TEMP RETRY WITH TEMP ORDER HEADER ID ".$orderheader['order_header_id'].", ";
																	}
										
																	$history = "INSERT INTO orderhistory(order_header_id, status_id, updated_by, update_date, created_date, created_by, modified_by) VALUES(?, ?, 'SYSTEM API', ?, ?, ".$orderheader['created_by'].", ".$orderheader['modified_by'].")";
																	$stmt7   = $this->db_master->prepare($history);
																	$stmt7->execute([$orderheader['order_header_id'], $orderheader['status_id'], date('Y-m-d H:i:s', strtotime($orderheader['created_date'])), date('Y-m-d H:i:s', strtotime($orderheader['created_date']))]);
																	if($stmt7->rowCount() == 0)
																	{
																		$error .= "CANNOT CREATE HISTORY WITH TEMP ORDER HEADER ID ".$orderheader['order_header_id'].", ";	
																	}
																}
										
																foreach ($order['items'] as $value) 
																{
                                                                    $stmt_checkItem = $this->db_master->prepare("SELECT a.item_id FROM item a LEFT JOIN itemmanaged b ON a.item_managed_id = b.item_managed_id WHERE a.item_id = :item_id AND a.client_id = :client_id");
                                                                    $stmt_checkItem->execute([
																		":item_id"   => $value['item_id'],
																		":client_id" => $client['client_id']
																	]);
                                                                    $checkItem = $stmt_checkItem->fetch();
																	if($stmt_checkItem->rowCount() > 0)
																	{
																		$stmt_createDetail = $this->db_master->prepare("INSERT INTO orderdetail(order_code, inventory_id, order_header_id, item_id, order_quantity, unit_price, total_unit_price, unit_weight, status_id, created_date, modified_date, created_by, modified_by, ref_detail_id) VALUES (:order_code,  NULL, :order_header_id, :item_id, :order_quantity, :unit_price, :total_unit_price, :unit_weight, :status_id, :created_date, :modified_date, :created_by, :modified_by, :reference_id)");
																		$stmt_createDetail->execute([
																			":order_code"       => strtoupper($order['order_code']),
																			":order_header_id"  => $orderheader['order_header_id'],
																			":item_id"          => $checkItem['item_id'],
																			":order_quantity"   => $value['quantity'],
																			":unit_price"       => $value['unit_price'],
																			":total_unit_price" => intval($value['quantity']*$value['unit_price']),
																			":unit_weight"      => $value['item_weight'],
																			":status_id"        => $orderheader['status_id'],
																			":created_date"     => $orderheader['created_date'],
																			":modified_date"    => $orderheader['modified_date'],
																			":created_by"       => $orderheader['created_by'],
																			":modified_by"      => $orderheader['modified_by'],
																			":reference_id"     => $value['ref_detail_id']
																		]);
																		if($stmt_createDetail->rowCount() == 0)
																		{
																			$error .= "FAILED CREATE ORDER DETAIL FOR ITEM ID ".$value['item_id'].", ";
																		}
																	}
																	else
																	{
																		$error .= "ITEM ".$value['item_id']." NOT FOUND IN ITEM MASTER, ";
																	}
																}
															}
															else
															{
																$error .= "CANNOT CREATE ORDER HEADER WITH INV CODE ".$order['order_code'].", ";
															}
														}
														else
														{
															$error .= "ORDER ".$order['order_code']." ALREADY EXISTS, ";
														}
													}
													else
													{
														$error .= "STATUS WAS NOT FOUND, ";

														$stmt_status = $this->db_slave->prepare("SELECT status_id FROM status WHERE code = 'ORD_UNFULFILLED'");
														$stmt_status->execute();
														$status = $stmt_status->fetch();
														if($stmt_status->rowCount() > 0)
														{
															$redis->set("status:ORD_UNFULFILLED", $status['status_id']);
															goto retryStatus;
														}
													}
												}
												else
												{
													$error .= "LOCATION ".$order['location_code']." NOT FOUND, ";

													$stmt_location = $this->db_slave->prepare("SELECT location_id FROM location WHERE code = :location_code");
													$stmt_location->execute([
														":location_code" => $order['location_code']
													]);
													$location = $stmt_location->fetch();
													if($stmt_location->rowCount() > 0)
													{
														$redis->set("location:".$order['location_code'], $location['location_id']);										goto retryLocation;																	
													}
												}
											}
											else
											{
												$error .= "ORDER TYPE ".$order['order_type']." NOT FOUND, ";

												$stmt_ordertype = $this->db_slave->prepare("SELECT order_type_id FROM ordertype WHERE name = :order_type");
												$stmt_ordertype->execute([
													":order_type" => $order['order_type']
												]);
												$ordertype = $stmt_ordertype->fetch();
												if($stmt_ordertype->rowCount() > 0)
												{
													$redis->set("ordertype:".$order['order_type'], $ordertype['order_type_id']);							
													goto retryOrderType;		
												}	
											}
										}
										else
										{
											$error .= "DELIVERY TYPE ".$order['delivery_type']." WITH COURIER ".$order['courier_name']." NOT FOUND, ";

											$stmt_deliverytype = $this->db_slave->prepare("SELECT delivery_type_id FROM deliverytype WHERE name = :delivery_type AND courier_id = :courier_id");
											$stmt_deliverytype->execute([
												":delivery_type" => $order['delivery_type'],
												":courier_id"    => $courier_id
											]);
											$deliverytype = $stmt_deliverytype->fetch();
											if($stmt_deliverytype->rowCount() > 0)
											{
                                                $redis->set("deliverytype:".$order['delivery_type'].":".$courier_id, $deliverytype['delivery_type_id']);
												goto retryDeliveryType;
											}
										}
									}
									else
									{
										$error .= "COURIER ".$order['courier_name']." NOT FOUND, ";

										$stmt_courier = $this->db_slave->prepare("SELECT courier_id FROM courier WHERE name = :courier_name");
										$stmt_courier->execute([
											":courier_name" => $order['courier_name']
										]);
										$courier = $stmt_courier->fetch();
										if($stmt_courier->rowCount() > 0)
										{
											$redis->set("courier:".$order['courier_name'], $courier['courier_id']);										
											goto retryCourier;
										}
									}
								}
								else
								{
									$error .= "STOCK TYPE ".$order['stock_type']." FOR CLIENT ".str_replace("'", "", $client['name'])." NOT FOUND, ";

									$stmt_stocktype = $this->db_slave->prepare("SELECT stock_type_id FROM stocktype WHERE name = :stock_type AND client_id = :client_id");
									$stmt_stocktype->execute([
										":stock_type" => $order['stock_type'],
										":client_id"  => $client['client_id']]);
									$stocktype    = $stmt_stocktype->fetch();
									if($stmt_stocktype->rowCount() > 0)
									{
										$redis->set("stocktype:".$client['client_id'].":".$order['stock_type'], $stocktype['stock_type_id']);
										goto retryStockType;
									}                                    
								}
							}
							else
							{
								$error .= "SHOP CONFIG FOR SHOP ".str_replace("'", "", $shop_name)." WITH CHANNEL ".$order['channel_name']." NOT FOUND, ";

								$stmt_shopconfiguration = $this->db_slave->prepare("SELECT shop_configuration_id FROM shopconfiguration WHERE client_id = :client_id AND channel_id = :channel_id AND shop_name = :shop_name");
								$stmt_shopconfiguration->execute([
									":client_id"  => $client['client_id'],
									":channel_id" => $channel_id['channel_id'],
									":shop_name"  => $order['shop_name']
								]);
								$shopconfiguration = $stmt_shopconfiguration->fetch();
								if($stmt_shopconfiguration->rowCount() > 0)
								{
									$redis->set("shopconfigID:".$client['client_id'].":".$channel_id.":".$shop_name, $shopconfiguration['shop_configuration_id']);
									goto retryShopConfig;
								}
							}
						}
						else
						{
							$error .= "CHANNEL ".$order['channel_name']." NOT FOUND, ";

							$stmt_channel = $this->db_slave->prepare("SELECT channel_id FROM channel WHERE name = :channel_name");
							$stmt_channel->execute([":channel_name" => $order['channel_name']]);
							$channel      = $stmt_channel->fetch();
							if($stmt_channel->rowCount() > 0)
							{
								$redis->set("channel:".$order['channel_name'], $channel['channel_id']);
								goto retryChannel;
							}
						}
					}
					else
					{
						$error .= "PAYMENT ".$order['payment_type']." NOT FOUND, ";

						$stmt_paymenttype = $this->db_slave->prepare("SELECT payment_type_id FROM paymenttype WHERE name = :payment_type");
						$stmt_paymenttype->execute([":payment_type" => $order['payment_type']]);
						$payment_type = $stmt_paymenttype->fetch();
						if($stmt_paymenttype->rowCount() > 0)
						{
							$redis->set("paymenttype:".$order['payment_type'], $payment_type['payment_type_id']);
							goto retryPaymentType;
						}
					}
				}
				else
				{
					$error .= "CLIENT WITH APIKEY ".$order['apikey']." NOT FOUND, ";
				}

				if($error == "")
				{
					$this->db_master->commit();

					$redis->set("orderheader:".$order['order_code'].":".$client['client_id'], date('Y-m-d H:i:s', strtotime($orderheader['created_date'])), 'EX', 259200);

					$msg = array(
						"status"  => 200,
						"message" => $order['shop_name']." - ORDER ".$order['order_code']." SUCCESS CREATED !",
						"data"    => [
							"order_id" => $orderheader['order_header_id']
						]
					);
					
					return $msg;
				}
				else
				{
					$this->db_master->rollBack();

					$itemList = json_encode($itemList);
					$itemList = trim($itemList, "[");
					$itemList = trim($itemList, "]");
					$itemList = str_replace('"', "", $itemList);

					$stmt3 = $this->db_master->prepare("INSERT INTO logapi(client_id, shop_configuration_id, order_code, item_code, result, created_date) VALUES (:client_id, :shop_configuration_id, :order_code, :item_code, :result, NOW())");
					$stmt3->execute([
						":client_id"             => $client['client_id'],
						":shop_configuration_id" => $shopconfiguration_id,
						":order_code"            => $order['order_code'],
						":item_code"             => json_encode($itemList),
						":result"                => trim($error, ", ")
					]);

					$msg = array(
                        "status"  => 400,
                        "message" => "CANNOT CREATE ORDER ".$order['order_code'].", BECAUSE: ".trim($error, ", "),
                        "data"    => null
					);

					return $msg;
				}
			}
			catch (PDOException $e) 
			{
				$this->db_master->rollBack();
				
				$msg = array(
					"status"  => 500,
					"message" => "CANNOT CREATE ORDER ".$order['order_code'].", BECAUSE: INTERNAL SERVER",
					"data"    => [
						"error_log" => $e->getMessage()
					]
				);

				return $msg;
			}
		}
    }
}