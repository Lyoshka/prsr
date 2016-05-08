<?php

// Имя EXCEL файла с ценами
$in_xls_file = "zelenogorsk.xlsx";

// ID города
$CITY_ID = 2;		


// Стоимость доставки в рублях, прибавляется к стоимости
$DELIVERY = 450;		

// Наценка
$price1 = 600;		// Наценка от 0 до 2000 р.
$price2 = 500;		// Наценка от 2000 до 3000 р.
$price3 = 400;		// Наценка от 3000 и выше



// ********************************************************************************************


$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

include_once(  'lib/PHPExcel/IOFactory.php');


$IBLOCK_ID 		= 13;
$IBLOCK_ID_TORG = 14;


$i=0;
$new_arr = array();


$new_arr = load_excel( $in_xls_file );

$ex_arr = array('CML2_LINK','ARTNUMBER','size_bouget','SALELEADER','MANUFACTURER','MINIMUM_PRICE','SIZE_BOUQUET');

if (CModule::IncludeModule("iblock")):



	$el = new CIBlockElement;
	
	logger("******************************************************************************************");
	
	$rsBooks = CIBlockElement::GetList(
	array("ID" => "ASC"), //Сортируем по имени
	array(
	  "IBLOCK_ID" => $IBLOCK_ID
	),
	false, 		// Без группировки
	false,  	//Без постранички
	array("ID","NAME","XML_ID") // Выбираем только поля необходимые для показа
	);
	  
	while($arBook = $rsBooks->GetNext()) {
		logger( $arBook["ID"] . ". " . $arBook["NAME"]);
		
			$PRODUCT_ID = $arBook["ID"];
			$PID = $PRODUCT_ID;
			$price_arr = array();
			$all_price = 0;
			$i = 0;
			$flag_zero = false;
			$price = 0;
			$XML_ID = $arBook["XML_ID"] . "-1";
			
			if ( !check_new_XMLID( $IBLOCK_ID, $XML_ID )  ) {
		
				$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array("sort" => "asc"));
						
				while ( $ob = $res->GetNext() ) {
					if (  $ob['VALUE'] && !in_array($ob['CODE'],$ex_arr) ) {
						
						$price_arr[] = array ( $ob['NAME'], $ob['VALUE']); 
						//logger(" 1 -- [" . $ob['CODE'] . "] " . $ob['NAME'] . " = " . $ob['VALUE'] . " Price: " . get_cost($ob['NAME'],$ob['VALUE']) );
						
						if (  get_cost($ob['NAME'],$ob['VALUE']) == 0 ) {
							$flag_zero = true;
						}
						
						$all_price += get_cost($ob['NAME'],$ob['VALUE']);
						$i++;
					}
				}
			
			} else {
		
				//print "Ищем торговое предложение к товару: " . $PRODUCT_ID . " XML_ID: " . $XML_ID . "<br>";
				$NEW_ID =  find_torg( $IBLOCK_ID_TORG, $XML_ID ) ;
				$PID = $NEW_ID;
				
				//$PRODUCT_ID = $arBook["ID"];
				$price_arr = array();
				$all_price = 0;
				//$i = 0;
				//$XML_ID = $arBook["XML_ID"] . "-1";
			
				$res = $el->GetProperty($IBLOCK_ID_TORG,$NEW_ID,array("sort" => "asc"));
						
				while ( $ob = $res->GetNext() ) {
					if (  $ob['VALUE'] && !in_array($ob['CODE'],$ex_arr) ) {
						
						$price_arr[] = array ( $ob['NAME'], $ob['VALUE']); 
						logger(" 2 -- [" . $ob['CODE'] . "] " . $ob['NAME'] . " = " . $ob['VALUE'] . " Price: " . get_cost($ob['NAME'],$ob['VALUE']) );
						
						if (  get_cost($ob['NAME'],$ob['VALUE']) == 0 ) {
							$flag_zero = true;
						}
						
						$all_price += get_cost($ob['NAME'],$ob['VALUE']);
						$i++;
					}
				}

				
			}
			
			if ( $i != 0 ) {
				
					$db_res = CPrice::GetList(
					array(),
					array(
							"PRODUCT_ID" => $PRODUCT_ID,
							"CATALOG_GROUP_ID" => $CITY_ID,
						));
					if ($ar_res = $db_res->Fetch())
					{
						$price = $ar_res["PRICE"];
					} 
							
				if ( $all_price == 0 || $flag_zero) {
					
					logger(" 1 --OLD PRICE: " . $price . " NEW PRICE: *********************  " . $all_price );
					update_price( $PID, 0, $CITY_ID );
					
				} else {
					
					
					
					$all_price += $DELIVERY;		// Добавляем доставку
					
					$all_price += get_margin( $all_price );		// Добавляем наценку
					
					logger(" 1 --OLD PRICE: " . $price . " NEW PRICE: " . $all_price );
					update_price( $PID, $all_price, $CITY_ID );
				}
				
			} else {
				logger(" 1 -- SKIP ");
			}

			
	}
	  
	  
	  // *********************************************************************************************************************
	  // торговые предложения
	  // *********************************************************************************************************************
	
	$IBLOCK_ID 		= $IBLOCK_ID_TORG;
	  
	$rsBooks = CIBlockElement::GetList(
	array("ID" => "ASC"), //Сортируем по имени
	array(
	  "IBLOCK_ID" => $IBLOCK_ID
	),
	false, 		// Без группировки
	false,  	//Без постранички
	array("ID","NAME","XML_ID") // Выбираем только поля необходимые для показа
	);
	  
	  while($arBook = $rsBooks->GetNext()) {
		logger( $arBook["ID"] . ". " . $arBook["NAME"]);
		
			$PRODUCT_ID = $arBook["ID"];
			$PID = $PRODUCT_ID;
			$price_arr = array();
			$all_price = 0;
			$flag_zero = false;
			$i = 0;
			$price = 0;
		
				$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array("sort" => "asc"));
						
				while ( $ob = $res->GetNext() ) {
					if (  $ob['VALUE'] && !in_array($ob['CODE'],$ex_arr) ) {
						
						$price_arr[] = array ( $ob['NAME'], $ob['VALUE']); 
						//logger(" 2 -- [" . $ob['CODE'] . "] " . $ob['NAME'] . " = " . $ob['VALUE'] . " Price: " . get_cost($ob['NAME'],$ob['VALUE']) );
						$all_price += get_cost($ob['NAME'],$ob['VALUE']);
						$i++;
					}
				}
			
			
			
			if ( $i != 0 ) {
				
					$db_res = CPrice::GetList(
					array(),
					array(
							"PRODUCT_ID" => $PRODUCT_ID,
							"CATALOG_GROUP_ID" => $CITY_ID,
			
						));
					if ($ar_res = $db_res->Fetch())
					{
						$price = $ar_res["PRICE"];
					} 

				if ( $all_price == 0 || $flag_zero ) {
					
					
					logger(" 2 --OLD PRICE: " . $price . " NEW PRICE: *********************  " . $all_price );
					update_price( $PID, 0, $CITY_ID );
					
				} else {
					
					$all_price += $DELIVERY;	// Добавляем доставку
					
					$all_price += get_margin( $all_price );		// Добавляем наценку
				
					logger(" 2 --OLD PRICE: " . $price . " NEW PRICE: " . $all_price );
					update_price( $PID, $all_price, $CITY_ID );
				}
				
			} else {
				logger(" 2 -- SKIP ");
			}

			
	  }
		  
endif;



require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");


// Функция определения наценки
function get_margin( $price ) {
	
	global $price1;
	global $price2;
	global $price3;
	
	$margin_arr[] = array ("min" => "0",    "max" => "2000", "price" => $price1,);
	$margin_arr[] = array ("min" => "2000", "max" => "3000", "price" => $price2,);
	$margin_arr[] = array ("min" => "3000", "max" => "100000", "price" => $price3,);
	
	
	for ($i=0;$i<count($margin_arr);$i++) {
		
		if ( $price > $margin_arr[$i]['min'] && $price <= $margin_arr[$i]['max']   ) {
			
			//logger("MIN: ". $margin_arr[$i]['min'] . " MAX: " . $margin_arr[$i]['max'] . " PRICE: " . $price );
			//logger("RETURN MARGIN: " . $margin_arr[$i]['price']);
			
			return $margin_arr[$i]['price'];
		}
	}
	

}


// Функция поиска торгового предложения к Базовому товару
function find_torg ( $IBLOCK_ID, $xml_id ) {
	
	
	CModule::IncludeModule("iblock");
	
	$rsBooks = CIBlockElement::GetList(
	array("NAME" => "ASC"), //Сортируем по имени
	array(
	  "IBLOCK_ID" => $IBLOCK_ID,
	  "ACTIVE" => "Y",
	  "ID" => CIBlockElement::SubQuery("ID", array(
				"XML_ID" => $xml_id,
			))
	),
	false, 		// Без группировки
	false,  	//Без постранички
	array("ID") // Выбираем только поля необходимые для показа
	);
	  
	  while($arBook = $rsBooks->GetNext())
		$PID =  $arBook["ID"];
			
		if ( $PID == null ) { 	// Если в базе нет такого XML_ID, то добавляем товар	
			return false;
		} else {
			return $PID;
		}
	
	
}


// Функция вычисления стоимости товара
function get_cost ($name, $amount) {
	
global $new_arr;
$flag = false;

	for($i=0;$i<count($new_arr);$i++) {
		
		if ( $new_arr[$i][0] == $name ) {
			$flag = true;
			
		if ( $new_arr[$i][1] == 0 ) {
			logger( "Обнаружено свойство с нулевой стоимостью: " . $name );
		}	
			
			return $new_arr[$i][1] * $amount;
			
		}
		
	}
	
	logger ("Свойство не найдено в EXCEL: " . $name );
	return 0;
}


function load_excel ( $in_xls_file ) {
	
	$xls = PHPExcel_IOFactory::load($in_xls_file);
	// Устанавливаем индекс активного листа
	$xls->setActiveSheetIndex(0);
	// Получаем активный лист
	$sheet = $xls->getActiveSheet();

    $array = array();
	
	// Получили строки и обойдем их в цикле
	$rowIterator = $sheet->getRowIterator();
	foreach ($rowIterator as $row) {
		// Получили ячейки текущей строки и обойдем их в цикле
		$cellIterator = $row->getCellIterator();
	 
		$item = array();
		foreach ($cellIterator as $cell) {
			//echo $cell->getCalculatedValue() . "<br>";
			array_push($item, $cell->getCalculatedValue());
		}
	
		array_push($array, $item);
	}
 return $array;
	
}

// *********************************************************************************************************************
// Функция обновления цены
// *********************************************************************************************************************	
function update_price( $PID, $new_price, $CITY_ID ) {

	CModule::IncludeModule("catalog");
	
	$PRODUCT_ID = $PID;
	$PRICE_TYPE_ID = $CITY_ID;

	$arFields = Array(
		"PRODUCT_ID" => $PRODUCT_ID,
		"CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
		"PRICE" => $new_price,
		"CURRENCY" => "RUB",
	);

	$res = CPrice::GetList(
			array(),
			array(
					"PRODUCT_ID" => $PRODUCT_ID,
					"CATALOG_GROUP_ID" => $PRICE_TYPE_ID
				)
		);

	if ($arr = $res->Fetch())
	{
		CPrice::Update($arr["ID"], $arFields);
	} else {

		CPrice::Add($arFields);
	}

}

function check_new_XMLID( $IBLOCK_ID, $in_XML ) {
		
	CModule::IncludeModule("iblock");
	
	$rsBooks = CIBlockElement::GetList(
	array("NAME" => "ASC"), //Сортируем по имени
	array(
	  "IBLOCK_ID" => $IBLOCK_ID,
	  "ACTIVE" => "Y",
	  "ID" => CIBlockElement::SubQuery("ID", array(
				"XML_ID" => $in_XML,
			))
	),
	false, 		// Без группировки
	false,  	//Без постранички
	array("ID") // Выбираем только поля необходимые для показа
	);
	  
	  while($arBook = $rsBooks->GetNext())
		$PID =  $arBook["ID"];
			
		if ( $PID == null ) { 	// Если в базе нет такого XML_ID, то добавляем товар	
			return false;
		} else {
			return $PID;
		}

}	

function logger($text) {
	file_put_contents("set_price.txt", date("Y-m-d H:i:s - ").$text."\n", FILE_APPEND);
}

?>

