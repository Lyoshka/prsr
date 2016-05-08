#!/usr/bin/php
<?php

	// Version 1.4
	
	$DOCUMENT_ROOT  = "/home/z/zhdanov/buketis.ru/public_html";
	
	$IBLOCK_ELEMENT  = 13;		// IBLOCK - товара
	$IBLOCK_PRODUCT  = 14;		// IBLOCK - торгового предложения
	$IBLOCK_MANUFACT = 10;		// IBLOCK - производителей
	$ID_CATALOG_ELEM = 49;		// ID свойства "Элемент каталога"
	$ID_REZMER_BUKET = 57;		// ID свойства "Размер букета" 
	$ID_SMALL_BUKET  = 34;		// ID свойства "Малый букет"
	$ID_MEDIUM_BUKET = 35;		// ID свойства "Средний букет"
	$ID_BIG_BUKET    = 36;		// ID свойства "Большой букет"
	$STORE_AMOUNT    = 1;		// Количество товара на складе
	$PRODUCT_AMOUNT_BASE   = 25;		// Количество базового товара 
	$PRODUCT_AMOUNT_SMALL  = 100;		// Количество малого товара 
	$PRODUCT_AMOUNT_MIDDLE = 50;		// Количество среднего товара 
	$PRODUCT_AMOUNT_BIG    = 25;		// Количество большого товара 

	
	/*
	$DOCUMENT_ROOT  = "D:/OpenServer/domains/bitrix.loc";
	
	$IBLOCK_ELEMENT  = 2;		// IBLOCK - товара
	$IBLOCK_PRODUCT  = 3;		// IBLOCK - торгового предложения
	$IBLOCK_MANUFACT = 10;		// IBLOCK - производителей
	$ID_CATALOG_ELEM = 31;		// ID свойства "Элемент каталога"
	$ID_REZMER_BUKET = 37;		// ID свойства "Размер букета" 
	$ID_SMALL_BUKET  = 17;		// ID свойства "Малый букет"
	$ID_MEDIUM_BUKET = 18;		// ID свойства "Средний букет"
	$ID_BIG_BUKET    = 19;		// ID свойства "Большой букет"
	$STORE_AMOUNT    = 1;		// Количество товара на складе
	$PRODUCT_AMOUNT_BASE   = 25;		// Количество базового товара 
	$PRODUCT_AMOUNT_SMALL  = 100;		// Количество малого товара 
	$PRODUCT_AMOUNT_MIDDLE = 50;		// Количество среднего товара 
	$PRODUCT_AMOUNT_BIG    = 25;		// Количество большого товара 
	
	*/


	define("NO_KEEP_STATISTIC", true);
	define("NOT_CHECK_PERMISSIONS", true);

	set_time_limit(0);		

	require_once dirname(__FILE__) . '/lib/curl_query.php';
	require_once dirname(__FILE__) . '/lib/simple_html_dom.php';
	require_once dirname(__FILE__) . '/catalogs.php';
	
	require_once($DOCUMENT_ROOT."/bitrix/modules/main/include/prolog_before.php");

	
	date_default_timezone_set('Europe/Moscow');
	
	$save_dir = $DOCUMENT_ROOT . '/images/';		// Директория для сохранения файлов

	$img_download = true;							// Скачивать картинки или нет		

		
	$site = array();
	
	$site[] = 'https://krasnojarsk.megaflowers.ru/filters/all'; 
	$site[] = 'https://krasnojarsk.megaflowers.ru/filters/drugie-tovary';

	
	$prod_arr = array();
	logger("****************************************************************************");

	for ($j=0;$j<count($site);$j++) {
	
		$html = curl_get($site[$j]);
		$html_base = new simple_html_dom();
		$html_base->load($html);


		$flovers = $html_base->find('.b-item-inner');
		// Инициализация массива товаров (заполняем с главной страницы)
		$i=0;
		foreach($flovers as $flover){
			
			
			$prodURL = $flover->find('.b-title a',0)->href;

			
			$prodName = $flover->find('.b-title a',0)->plaintext;
			
			$memoSmall = $flover->find('.b-descr p',0)->plaintext;
			
			$productID = $flover->find('a.b-small-order-btn',0)->attr['data-nodeid'];
			
			$priceMin    = preg_replace("/[^0-9]/i","",$flover->find('div[data-type=mini] span',0)->plaintext);
			$priceMiddle = preg_replace("/[^0-9]/i","",$flover->find('div[data-type=middle] span',0)->plaintext);
			$priceMax    = preg_replace("/[^0-9]/i","",$flover->find('div[data-type=maxi] span',0)->plaintext);
			$price       = preg_replace("/[^0-9]/i","",$flover->find('span[itemprop=price]',0)->plaintext);
			
			$mainImageURL = $flover->find('.b-image img',0)->src;

			if ( !$mainImageURL ) {	
				$mainImageURL = $flover->find('.image-lazy',0)->attr['data-original'];
			}		
			
			$model = preg_replace("/[^0-9]/i","",$flover->find('.js-bouquet-number',0)->plaintext);
			
			$prod_arr[] = new Product($productID,$prodName,$prodURL,$memoSmall,$price,$priceMin,$priceMiddle,$priceMax,$mainImageURL,$model);
			
				//if ( $i >= 10 ) { break; }
				
			$i += 1;
		}

		// Закончили работу с основной страницей
		$html_base->clear(); 
		unset($html_base);
	}
	
	logger( "Загрузили основную страницу");

	$flag_load = false;
	
	//********************************************************************************************************************************
	// Проходим по массиву и если находим новый продукт начинем загружать страницы товаров
	//for($i=0;$i<10;$i++) {  
	for($i=0;$i<count($prod_arr);$i++) {	
	
	
			// Проверка и обновление цены
			//check_new_price( $prod_arr[$i], $i );
			
			

		//if ( !check_new_XMLID( $IBLOCK_ELEMENT, $prod_arr[$i]->get_ID() ) ) {		// Проверяем XML_ID, если в базе нет подгружаем страницу товара

				$html = curl_get($prod_arr[$i]->get_URL());
				$html_base = new simple_html_dom();
				$html_base->load($html);
				
				logger( "Load PAGE: " . $i );
				$flag_load = true;
				
				// Установка описания
				$prod_arr[$i]->set_description($html_base->find('div[itemprop=description] p',0)->plaintext);
				
				$prod_arr[$i]->set_SEO($html_base->find('input[id=bname]',0)->attr['value']);
				
				// Установка каталогов
				$flovers = $html_base->find('a[property=v:title]');
			
				foreach($flovers as $flover) {
					if ( trim($flover->plaintext)) { 
						$prod_arr[$i]->set_catalog(get_num_catalog($flover->plaintext));
						$prod_arr[$i]->set_manufacturer($flover->plaintext);
						}
				}
						
				// Установка состава SMALL
				$comp_smalls = $html_base->find('.sp-card-small li');
				foreach($comp_smalls as $comp_small) {
					$prod_arr[$i]->set_comp_small( $comp_small->find('.title',0)->plaintext, $comp_small->find('.amount',0)->plaintext ); 
				}

				// Установка состава MEDIUM
				$comp_smalls = $html_base->find('.sp-card-medium li');	
				foreach($comp_smalls as $comp_small) {
					$prod_arr[$i]->set_comp_medium( $comp_small->find('.title',0)->plaintext, $comp_small->find('.amount',0)->plaintext ); 
				}

				// Установка состава BIG
				$comp_smalls = $html_base->find('.sp-card-big li');	
				foreach($comp_smalls as $comp_small) {
					$prod_arr[$i]->set_comp_big( $comp_small->find('.title',0)->plaintext, $comp_small->find('.amount',0)->plaintext ); 
				}

				// Установка фото SMALL
				$foto_smalls = $html_base->find('.sp-card-photos .sp-card-small img');
				foreach($foto_smalls as $foto_small) {

					if ( !preg_match('/s(\d{3})/', $foto_small->src) ) {
						$prod_arr[$i]->set_foto_small($foto_small->src); 	
					}
				}

				// Установка фото MEDIUM
				$foto_smalls = $html_base->find('.sp-card-photos .sp-card-medium img');
				foreach($foto_smalls as $foto_small) {
					
					if ( !preg_match('/s(\d{3})/', $foto_small->src) ) {
						if ( strpos( $foto_small->src, "_b" ) ) {
							$prod_arr[$i]->set_mainImageURL($foto_small->src);
						}
						
						$prod_arr[$i]->set_foto_medium($foto_small->src); 

					}
					
				}

				// Установка фото BIG
				$fotos = $html_base->find('.sp-card-photos .sp-card-big img');
				foreach($fotos as $foto) {
					if ( !preg_match('/s(\d{3})/', $foto->src) ) {
						$prod_arr[$i]->set_foto_big($foto->src); 	
					}
				}
				
				// Установка цены SMALL
				$prod_arr[$i]->set_data_small($html_base->find('li[data-key=small]',0)->plaintext, $html_base->find('.sp-card-small .price',0)->plaintext); 
				// Установка цены MEDIUM
				$prod_arr[$i]->set_data_medium($html_base->find('li[data-key=medium]',0)->plaintext, $html_base->find('.sp-card-medium .price',0)->plaintext); 
				// Установка цены BIG
				$prod_arr[$i]->set_data_big($html_base->find('li[data-key=big]',0)->plaintext, $html_base->find('.sp-card-big .price',0)->plaintext); 
		
				$html_base->clear(); 
				unset($html_base);
		//}
	}
	
	if ( $flag_load ) {
		
		
		// Загрузка изображений в локальный каталог
		//load_images( $prod_arr );
	
		// Установка дополнительных каталогов
		//catalog_sync( $prod_arr );

		// Переиндексация
		//$NS = CSearch::ReIndexAll(false);
		//logger ("Переиндексация " . $NS );

	}
	
	for($i=0;$i<count($prod_arr);$i++) {
		
		check_prod ($prod_arr[$i]);
	
	}

	//$count_prod = save_to_bitrix( $prod_arr );
	
	
	logger( "Добавлено " . $count_prod . " новых продуктов");


	
// *********************************************************************************************************************
// Функция проверки новой ЦЕНЫ
// *********************************************************************************************************************	
function check_prod( $in_array ) {

	global $IBLOCK_ELEMENT;
	global $IBLOCK_PRODUCT;
	
	//$IBLOCK_ID = 3;
	

	CModule::IncludeModule("catalog");
	CModule::IncludeModule("iblock");

	
	$PID = check_new_XMLID( $IBLOCK_ELEMENT, $in_array->get_ID() );
	$el = new CIBlockElement;
	
	
	//Проверка ID базового товара
	if ( $PID )  {
		
			$PRODUCT_ID = $PID;
		
			logger( $PRODUCT_ID );
		
		
			$res = CIBlockElement::GetProperty($IBLOCK_ELEMENT,$PRODUCT_ID,array("sort" => "asc"));
			
			//var_dump ($in_array);
			$flag = false;
			$array_prop = array();
					
			while ( $ob = $res->GetNext() ) {
				
				if ( $ob['VALUE'] ) {
					$array_prop[] = $ob['NAME'];
					//logger(" -- " . $ob['NAME'] . " -- " . $ob['VALUE']);
				}
			
			}
			
			if ( count($in_array->get_comp_small()) > 0 ) {
						
				for ($i=0;$i<count($in_array->get_comp_small());$i++) {
						
						
						if (  !in_array ($in_array->get_comp_small()[$i][0], $array_prop  ) ) {
							
							
							logger ("NOT FOUND: " . $in_array->get_comp_small()[$i][0] . " = " . $in_array->get_comp_small()[$i][1]);
						
							if  ( !check_prop( $IBLOCK_ELEMENT, $in_array->get_comp_small()[$i][0] ) ) {
								
								add_new_prop( $IBLOCK_ELEMENT, $in_array->get_comp_small()[$i][0] );
							}
							
							add_element_prop( $PRODUCT_ID, $IBLOCK_ELEMENT, $in_array->get_comp_small()[$i][0], $in_array->get_comp_small()[$i][1] );
				
				
						}
				
				}
			} else {
				
				for ($i=0;$i<count($in_array->get_comp_medium());$i++) {
						
						
						if (  !in_array ($in_array->get_comp_medium()[$i][0], $array_prop  ) ) {
							
							
							logger ("NOT FOUND: " . $in_array->get_comp_medium()[$i][0]);
							
							if  ( !check_prop( $IBLOCK_ELEMENT, $in_array->get_comp_medium()[$i][0] ) ) {
								
								add_new_prop( $IBLOCK_ELEMENT, $in_array->get_comp_medium()[$i][0] );
							}
							
							add_element_prop( $PRODUCT_ID, $IBLOCK_ELEMENT, $in_array->get_comp_medium()[$i][0], $in_array->get_comp_medium()[$i][1] );
		
				
						}
				
				}
				
			}	
			
				
		
		
		
		//***********************************************************************************
		// Проверяем цену МАЛОГО товара 
		$PID = check_new_XMLID( $IBLOCK_PRODUCT, $in_array->get_ID() . "-1" );
		
		if ( $PID )  {
			
			$PRODUCT_ID = $PID;
			
			logger( $PRODUCT_ID );
			
			$res = CIBlockElement::GetProperty($IBLOCK_PRODUCT,$PRODUCT_ID,array("sort" => "asc"));
			
			//var_dump ($in_array);
			$flag = false;
			$array_prop = array();
					
			while ( $ob = $res->GetNext() ) {
				
				if ( $ob['VALUE'] ) {
					$array_prop[] = $ob['NAME'];
					//logger(" -- " . $ob['NAME'] . " -- " . $ob['VALUE']);
				}
			
			}
			
					
			for ($i=0;$i<count($in_array->get_comp_small());$i++) {
					
					
					if (  !in_array ($in_array->get_comp_small()[$i][0], $array_prop  ) ) {
						
						
						logger ("NOT FOUND: " . $in_array->get_comp_small()[$i][0]);
						
							if  ( !check_prop( $IBLOCK_PRODUCT, $in_array->get_comp_small()[$i][0] ) ) {
								
								add_new_prop( $IBLOCK_PRODUCT, $in_array->get_comp_small()[$i][0] );
							}
							
							add_element_prop( $PRODUCT_ID, $IBLOCK_PRODUCT, $in_array->get_comp_small()[$i][0], $in_array->get_comp_small()[$i][1] );
		
			
					}
			}

			
		}	
			
		// Проверяем цену СРЕДНЕГО товара 
		$PID = check_new_XMLID( $IBLOCK_PRODUCT, $in_array->get_ID() . "-2" );
			
		if ( $PID )  {
			
			$PRODUCT_ID = $PID;
			
			logger( $PRODUCT_ID );
			
			$res = CIBlockElement::GetProperty($IBLOCK_PRODUCT,$PRODUCT_ID,array("sort" => "asc"));
			
			//var_dump ($in_array);
			$flag = false;
			$array_prop = array();
					
			while ( $ob = $res->GetNext() ) {
				
				if ( $ob['VALUE'] ) {
					$array_prop[] = $ob['NAME'];
					//logger(" -- " . $ob['NAME'] . " -- " . $ob['VALUE']);
				}
			
			}
			
					
			for ($i=0;$i<count($in_array->get_comp_medium());$i++) {
					
					
					if (  !in_array ($in_array->get_comp_medium()[$i][0], $array_prop  ) ) {
						
						
						logger ("NOT FOUND: " . $in_array->get_comp_medium()[$i][0]);
							if  ( !check_prop( $IBLOCK_PRODUCT, $in_array->get_comp_medium()[$i][0] ) ) {
								
								add_new_prop( $IBLOCK_PRODUCT, $in_array->get_comp_medium()[$i][0] );
							}
							
							add_element_prop( $PRODUCT_ID, $IBLOCK_PRODUCT, $in_array->get_comp_medium()[$i][0], $in_array->get_comp_medium()[$i][1] );
				
					}
			}

			
		}	
			// Проверяем цену БОЛЬШОГО товара 
			$PID = check_new_XMLID( $IBLOCK_PRODUCT, $in_array->get_ID() . "-3" );
			
		if ( $PID )  {
			
			$PRODUCT_ID = $PID;
			
			logger( $PRODUCT_ID );
			
			$res = CIBlockElement::GetProperty($IBLOCK_PRODUCT,$PRODUCT_ID,array("sort" => "asc"));
			
			//var_dump ($in_array);
			$flag = false;
			$array_prop = array();
					
			while ( $ob = $res->GetNext() ) {
				
				if ( $ob['VALUE'] ) {
					$array_prop[] = $ob['NAME'];
					//logger(" -- " . $ob['NAME'] . " -- " . $ob['VALUE']);
				}
			
			}
			
					
			for ($i=0;$i<count($in_array->get_comp_big());$i++) {
					
					
					if (  !in_array ($in_array->get_comp_big()[$i][0], $array_prop  ) ) {
						
						
						logger ("NOT FOUND: " . $in_array->get_comp_big()[$i][0]);
							
							if  ( !check_prop( $IBLOCK_PRODUCT, $in_array->get_comp_big()[$i][0] ) ) {
								
								add_new_prop( $IBLOCK_PRODUCT, $in_array->get_comp_big()[$i][0] );
							}
							
							add_element_prop( $PRODUCT_ID, $IBLOCK_PRODUCT, $in_array->get_comp_big()[$i][0], $in_array->get_comp_big()[$i][1] );
				
					}
			}

			
		}
			
			
		
	} else {
		
		// Товар с таким XML_ID не найден
		
	}

}	

	
// *********************************************************************************************************************
// Функция обновления срировкм
// *********************************************************************************************************************	
function sort_update( $PID, $SORT ) {


		CModule::IncludeModule("iblock");
		

		$PRODUCT_ID = $PID; 
		$SOTR_ORDER = $SORT;

		$el = new CIBlockElement;
		
		$arLoadProductArray = Array(
		  "SORT"           => $SOTR_ORDER,
		  );

		$res = $el->Update($PRODUCT_ID, $arLoadProductArray);
		  

}


	
// *********************************************************************************************************************
// Функция обновления цены
// *********************************************************************************************************************	
function update_price( $PID, $new_price ) {

	CModule::IncludeModule("catalog");
	
	$PRODUCT_ID = $PID;
	$PRICE_TYPE_ID = 1;

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


	
// *********************************************************************************************************************
// Функция проверки наличия товара с XML_ID в БД
// *********************************************************************************************************************	
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
	
// *********************************************************************************************************************
// Функция импорта в Bitrix
// *********************************************************************************************************************
function save_to_bitrix( $in_array ) {
	
	global $IBLOCK_ELEMENT;
	global $IBLOCK_PRODUCT;
	
	$count_prod = 0;	// Счетчик добавленных продуктов

	CModule::IncludeModule("iblock");

	for($i=0;$i<count($in_array);$i++) {

			
		if ( !check_new_XMLID( $in_array[$i]->get_ID() ) ) { 	// Если в базе нет такого XML_ID, то добавляем товар
		
			
			//***************************************************************************************
			// Добавляем новые названия атрибутов в справочник атрибутов: b_iblock_property
			//***************************************************************************************
			
				// Проврека наличия атрибута
				for($j=0;$j<count($in_array[$i]->get_comp_medium());$j++) {
					$attr_name = $in_array[$i]->get_comp_medium()[$j][0];
					if  ( !check_prop( $IBLOCK_ELEMENT, $attr_name ) ) {
						
						// Добавляе название атрибута 
						if ( add_new_prop( $IBLOCK_ELEMENT, $attr_name ) ) {
							//logger( "Добавлен новый атрибут: " . $attr_name );
						} 
						if ( add_new_prop( $IBLOCK_PRODUCT, $attr_name ) ) {
							//logger( "Добавлен новый атрибут: " . $attr_name );
						} 
						
					}

				}
			
			
			//***************************************************************************************
			// Добавляем новый: БАЗОВЫЙ товар
			//***************************************************************************************
				$PRODUCT_ID = prod_base_add ( $in_array[$i] );

				if ( $PRODUCT_ID ) {	// Базовый продукт добавлен успешно, начинаем добавлять торговые предложения
								
					$count_prod += 1;
					
					// Проверяем наличие 3-х вариантов букета
					if ( $in_array[$i]->get_priceMiddle() ) {
					
					// *********************************************************************************************
						
						// Добавляем Торговое предложение BIG
						$PROD_ADD_ID = prod_adv_add ( $PRODUCT_ID, $in_array[$i], 3);
						
						// Добавляем состав к торговому предложению BIG
						$attr_arr = $in_array[$i]->get_comp_big();
						
						for($j=0;$j<count($attr_arr);$j++) {
							add_element_prop( $PROD_ADD_ID, $IBLOCK_PRODUCT, $attr_arr[$j][0], $attr_arr[$j][1] );
						}

					// *********************************************************************************************
						
						// Добавляем Торговое предложение MEDIUM
						$PROD_ADD_ID = prod_adv_add ( $PRODUCT_ID, $in_array[$i], 2);
						
						// Добавляем состав к торговому предложению MEDIUM
						$attr_arr = $in_array[$i]->get_comp_medium();
						
						for($j=0;$j<count($attr_arr);$j++) {
							add_element_prop( $PROD_ADD_ID, $IBLOCK_PRODUCT, $attr_arr[$j][0], $attr_arr[$j][1] );
						}


					// *********************************************************************************************
						
						
						// Добавляем Торговое предложение SMALL
						$PROD_ADD_ID = prod_adv_add ( $PRODUCT_ID, $in_array[$i], 1);
						
						// Добавляем состав к торговому предложению SMALL
						$attr_arr = $in_array[$i]->get_comp_small();
						
						for($j=0;$j<count($attr_arr);$j++) {
							add_element_prop( $PROD_ADD_ID, $IBLOCK_PRODUCT, $attr_arr[$j][0], $attr_arr[$j][1] );
						}

					// *********************************************************************************************
						
						
					} else {			// Если нет ТОРГОВЫХ предложений, добавляем атрибуты на базовый товар
						
						// Добавляем состав к торговому предложению MEDIUM
						$attr_arr = $in_array[$i]->get_comp_medium();
						
						for($j=0;$j<count($attr_arr);$j++) {
							add_element_prop( $PRODUCT_ID, $IBLOCK_ELEMENT, $attr_arr[$j][0], $attr_arr[$j][1] );
						}
						

					}
					
				} 
		
		} else {		// IF если нет такого XML_ID 
			logger( "Такой ID уже есть, пропускаем"); 

		}	
	
	}

	return $count_prod;
	
}		

// ****************************************************************************************************
// Функция добавления количества товара на складе. Таблица: catalog_store_product 
// ****************************************************************************************************
function add_prod_store( $PID, $amount ) {

	CModule::IncludeModule("catalog");	
	
	$arFields = Array(
        "PRODUCT_ID" => $PID,
        "STORE_ID" => 1,
        "AMOUNT" => $amount,
    );
    
    $ID = CCatalogStoreProduct::Add($arFields);	 

}



// ****************************************************************************************************
// Функция добавления ТОРГОВОГО ПРЕДЛОЖЕНИЯ товара 
// ****************************************************************************************************

function prod_adv_add ( $PRODUCT_ID, $in_array, $type) {
	
	global $DOCUMENT_ROOT;
	global $IBLOCK_ELEMENT;
	global $IBLOCK_PRODUCT;
	global $ID_CATALOG_ELEM;
	global $ID_ARTIKUL_ADV;
	global $ID_SMALL_BUKET;
	global $ID_MEDIUM_BUKET;
	global $ID_BIG_BUKET;
	global $ID_REZMER_BUKET;
	global $STORE_AMOUNT;
	global $PRODUCT_AMOUNT_SMALL;	 
	global $PRODUCT_AMOUNT_MIDDLE;	 
	global $PRODUCT_AMOUNT_BIG;		 
	
	
	$artikul_dop = "-0";
	$prod_size = 0;
	$prod_price = 0;
	$xml_id		= 0;

				$el = new CIBlockElement; 		//определяем  новый элемент
				
				switch ( $type ) {
					case 1:
						$arr_foto = $in_array->get_foto_small();
						$artikul_dop = "-1";
						$prod_size = $ID_SMALL_BUKET;
						$prod_price = $in_array->get_priceMin();
						$xml_id	= $in_array->get_ID() . "-1";
						$PRODUCT_AMOUNT = $PRODUCT_AMOUNT_SMALL;
						break;
					case 2:
						$arr_foto = $in_array->get_foto_medium();
						$artikul_dop = "-2";
						$prod_size = $ID_MEDIUM_BUKET;
						$prod_price = $in_array->get_priceMiddle();
						$xml_id	= $in_array->get_ID() . "-2";
						$PRODUCT_AMOUNT = $PRODUCT_AMOUNT_MIDDLE;
						break;
					case 3:
						$arr_foto = $in_array->get_foto_big();
						$artikul_dop = "-3";
						$prod_size = $ID_BIG_BUKET;
						$prod_price = $in_array->get_priceMaxi();
						$xml_id	= $in_array->get_ID() . "-3";
						$PRODUCT_AMOUNT = $PRODUCT_AMOUNT_BIG;
						break;
				}
				
				for ($j=0;$j<count($arr_foto);$j++) {
					$detail_p = basename($arr_foto[$j]);
				}
				
				$arLoadProductArray = Array( 
											"MODIFIED_BY"    	=> 1,
											//"IBLOCK_SECTION_ID" => 1,
											"IBLOCK_ID"      	=> $IBLOCK_PRODUCT, 					/*тут ставите свой инфоблок*/
											"NAME"           	=> $in_array->get_Name(),
											"DETAIL_PICTURE" 	=> CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"]."/images/" . $detail_p ),                  
											//"PREVIEW_PICTURE" 	=> CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"]."/images/" . basename($in_array->get_mainImageURL () )),                  
											"ACTIVE"         	=> "Y", 
											"XML_ID"			=> $xml_id,
											);
				
				if($PROD_ADD_ID = $el->Add($arLoadProductArray, false, true, true)) {
					
									
					// Добавляем свойства
					$PROP = Array(); 	
					$PROP[$ID_CATALOG_ELEM] = $PRODUCT_ID; 
					$PROP["ARTNUMBER"]  	= $in_array->get_model() . $artikul_dop;
					$PROP[$ID_REZMER_BUKET]   = array("VALUE" => $prod_size, "VALUE_ENUM" => $prod_size); 
				
					$arLoadProductArray = Array("PROPERTY_VALUES"=> $PROP); 
					$el->Update($PROD_ADD_ID, $arLoadProductArray); 
					
					// Добавляем кол-во на складе
					add_prod_store( $PROD_ADD_ID, $STORE_AMOUNT );
					
				// **********************************************************************************
				// Добавляем ЦЕНУ и количество
				// **********************************************************************************				

						$PRICE_TYPE_ID = 1; //базовая валюта                  
						$arFields = Array(  "PRODUCT_ID" => $PROD_ADD_ID,                        
											"CATALOG_GROUP_ID" => 1,                        
											"PRICE" => $prod_price,                        
											"CURRENCY" => "RUB" 	/*код валюты*/                    );
						
						$res = CPrice::GetList( array(), array( "PRODUCT_ID" => $$PROD_ADD_ID,
																"CATALOG_GROUP_ID" => $PRICE_TYPE_ID  )
												); 

						if ($arr = $res->Fetch())                    
							{  
								CPrice::Update($arr["ID"], $arFields); 
							} else {
								CPrice::Add($arFields); 
						}                    
						
						//добавляем количество на складе (по умолчанию = 1) 
								
						$arFields = array(  "ID" => $PROD_ADD_ID,
											"QUANTITY"=> $PRODUCT_AMOUNT  );

						if(CCatalogProduct::Add($arFields))  {
								//echo "Добавили параметры товара к элементу каталога ".'<br>';
						}
					
					
					
					//echo "Add new ADD ID: ".$PROD_ADD_ID .'<br>';
					
				} else {
					logger( "Error add product: " . $el->LAST_ERROR );
				}
		
		return $PROD_ADD_ID;
				
}

// ****************************************************************************************************
// Функция добавления БАЗОВОГО товара 
// ****************************************************************************************************

function prod_base_add ( $in_array ) {
	
	global $DOCUMENT_ROOT;
	global $IBLOCK_ELEMENT;
	global $IBLOCK_PRODUCT;
	global $PRODUCT_AMOUNT_BASE; 
	global $STORE_AMOUNT;
	global $IBLOCK_MANUFACT;


				$el = new CIBlockElement; 		//определяем  новый элемент  
				$detail_p = basename($in_array->get_mainImageURL ());
		
				for ($j=0;$j<count($in_array->get_foto_big());$j++) {
					$detail_p = basename($in_array->get_foto_big()[$j]);
				}
				
				
				
				$arLoadProductArray = Array( 			"MODIFIED_BY"    	=> 1,
														"IBLOCK_SECTION_ID" => 1,
														"IBLOCK_ID"      	=> $IBLOCK_ELEMENT, 					/*тут ставите свой инфоблок*/
														"NAME"           	=> $in_array->get_Name(),
														"CODE"           	=> $in_array->get_SEO(),
														//"PROPERTY_VALUES"	=> $PROP,
														"DETAIL_PICTURE" 	=> CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"]."/images/" . $detail_p ),                  
														"PREVIEW_PICTURE" 	=> CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"]."/images/" . basename($in_array->get_mainImageURL () )),                  
														"ACTIVE"         	=> "Y", 
														"XML_ID"			=> $in_array->get_ID(),
														"PREVIEW_TEXT"		=> $in_array->get_memoSmall(),
														"DETAIL_TEXT"		=> $in_array->get_description(),
														);
				
				if($PRODUCT_ID = $el->Add($arLoadProductArray, false, true, true)) {
					
				
				// **********************************************************************************
				// Добавляем каталоги к БАЗОВОМУ товару
				// **********************************************************************************
				
							CIBlockElement::SetElementSection($PRODUCT_ID, $in_array->get_catalog() );
				
				
				// **********************************************************************************
				

							
						// Добавляем производителя
						$manufacturer_id = check_element( $IBLOCK_MANUFACT, $in_array->get_manufacturer() );
						
						//logger("MAN_ID: " . $manufacturer_id );
						
							
							$PROP = Array(); 	
							//$PROP["MORE_PHOTO"] = $arr_foto; 
							$PROP["ARTNUMBER"]  = $in_array->get_model();
							$PROP["MANUFACTURER"] = array("VALUE" => $manufacturer_id, "VALUE_ENUM" => $manufacturer_id);
							 
						
							$arLoadProductArray = Array("PROPERTY_VALUES"=> $PROP); 
							$el->Update($PRODUCT_ID, $arLoadProductArray); 

							// Добавляем кол-во на складе
							add_prod_store( $PRODUCT_ID, $STORE_AMOUNT );

							
				// *************************************************************************************
				
				
				logger( "Добавлен новый продукт ID: ".$PRODUCT_ID . " - " . $in_array->get_Name());
				  

				// **********************************************************************************
				// Добавляем ЦЕНУ и количество
				// **********************************************************************************				

						$PRICE_TYPE_ID = 1; //базовая валюта                  
						$arFields = Array(  "PRODUCT_ID" => $PRODUCT_ID,                        
											"CATALOG_GROUP_ID" => 1,                        
											"PRICE" => $in_array->get_Price(),                        
											"CURRENCY" => "RUB" 	/*код валюты*/                    );
						
						$res = CPrice::GetList( array(), array( "PRODUCT_ID" => $PRODUCT_ID,
																"CATALOG_GROUP_ID" => $PRICE_TYPE_ID  )
												); 

						if ($arr = $res->Fetch())                    
							{  
								CPrice::Update($arr["ID"], $arFields); 
							} else {
								CPrice::Add($arFields); 
						}                    
						
						//добавляем количество на складе (по умолчанию = 1) 
								
						$arFields = array(  "ID" => $PRODUCT_ID,
											"QUANTITY"=> $PRODUCT_AMOUNT_BASE  );

						if(CCatalogProduct::Add($arFields))  {
								//echo "Добавили параметры товара к элементу каталога ".'<br>';
						}
				return $PRODUCT_ID;
				
				} else {
					
					// Ошибка добавления товара
					return 0;
					
				}
}

// ****************************************************************************************************
// Функция загрузки изображений в локальный каталог
// ****************************************************************************************************

function load_images ( $prod_arr ) {

	$k=0;
	for($j=0;$j<count($prod_arr);$j++) {
	//for($j=0;$j<2;$j++) {

		
		$img_url = $prod_arr[$j]->get_mainImageURL();

		
		if (!empty( $img_url )) {
			//logger("---- Main: " . save_img ("http:".$prod_arr[$j]->get_mainImageURL()) );
			save_img ("http:".$prod_arr[$j]->get_mainImageURL() );
			$k += 1;
		
			if ( !empty($prod_arr[$j]->get_foto_big()[0] ) ) {
			
				for ($i=0;$i<count($prod_arr[$j]->get_foto_big());$i++) {
					
					//logger ("--- Big: ".save_img ("http:".$prod_arr[$j]->get_foto_big()[$i]) );
					save_img ("http:".$prod_arr[$j]->get_foto_big()[$i]);
					$k += 1;
					
				}
			
			}
			if ( !empty($prod_arr[$j]->get_foto_small()[0] ) ) {
			
				for ($i=0;$i<count($prod_arr[$j]->get_foto_small());$i++) {
					
					//logger("--- Small: ".save_img ("http:".$prod_arr[$j]->get_foto_small()[$i]) );
					save_img ("http:".$prod_arr[$j]->get_foto_small()[$i]);
					$k += 1;

				}
			
			}

		}
		
	}
	
	logger("Загружно новых изображений: " . $k);
	// ****************************************************************************************************	
}

// ************************************************************************************
// Функция проверки наличия Атрибута. Таблица: b_iblock_property
// ************************************************************************************
function check_prop( $IBLOCK_ID, $attr_name ) {
	
	
if (CModule::IncludeModule("iblock")):

	$NAME = $attr_name;
	
		$properties = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>$IBLOCK_ID, "NAME"=>$NAME));
		while ($prop_fields = $properties->GetNext())
		{
		  $ID = $prop_fields["ID"];
		}
			
	if ( $ID == null  ) {
		return false;
	} else {
		return $ID;
	}
	 
 
endif;	
	
}

// ************************************************************************************
// Функция проверки наличия Товара. Таблица: b_iblock_element
// ************************************************************************************
function check_element( $IBLOCK_ID, $element_name ) {
	
if (CModule::IncludeModule("iblock")):

	$NAME = $element_name;
	
		$properties = CIBlockElement::GetList(Array("sort"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>$IBLOCK_ID, "NAME"=>$NAME));
		while ($prop_fields = $properties->GetNext())
		{
		  $ID = $prop_fields["ID"];
		}
			
	if ( $ID == null  ) {
		return false;
	} else {
		return $ID;
	}
	 
 
endif;	
	
}

// ************************************************************************************
// Функция добавления нового Атрибута. Таблица: b_iblock_property
// ************************************************************************************
function add_new_prop( $IBLOCK_ID, $attr_name ) {
	
	
if (CModule::IncludeModule("iblock")):

	
	
	$arFields = Array(
		  "NAME" => $attr_name,
		  "ACTIVE" => "Y",
		  "SORT" => "100",
		  "CODE" => NormalizeString($attr_name),
		  "PROPERTY_TYPE" => "N",
		  "IBLOCK_ID" => $IBLOCK_ID
		  );
	
	$ibp = new CIBlockProperty;
	$PropID = $ibp->Add($arFields);
		
			
	if ( $PropID == false  ) {
		return false;
	} else {
		return true;
	}
	 
 
endif;	
	
}


// ************************************************************************************
// Функция добавдления значения Атрибута. Таблица: b_iblock_element_property
// ************************************************************************************
function add_element_prop( $PRODUCT_ID, $IBLOCK_ID, $ATTR_NAME, $ATTR_VALUE ) {
	
	
if (CModule::IncludeModule("iblock")):

					$el = new CIBlockElement;
		
					$ATTR_ID = check_prop( $IBLOCK_ID, $ATTR_NAME );
		
					// Добавляем свойства
					$PROP = Array(); 

					$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array("sort" => "asc"));
					
					while ( $ob = $res->GetNext() ) {
						if (  $ob['VALUE'] ) {
							
							$PROP[$ob['CODE']] = $ob['VALUE'];
							
							print $ob['NAME'] . " = " . $ob['VALUE'] . "<br>";
						}
					}
					
					$PROP[$ATTR_ID] = $ATTR_VALUE; 
				
					$arLoadProductArray = Array("PROPERTY_VALUES"=> $PROP); 
					if ($el->Update($PRODUCT_ID, $arLoadProductArray) ) {					
						return true;
					} else {
						logger( "ERROR ADD PROPERTY: " . $el->LAST_ERROR );
						return false;
					}
	 
 
endif;	
	
}


// ************************************************************************************
// Основной класс продукта
// ************************************************************************************
		
class Product {
	
	protected $site = 'https://megaflowers.ru';
	private $productID;
	private $productName;
	private $productURL;
	private $memoSmall;
	private $price;
	private $priceMin;
	private $priceMiddle;
	private $priceMaxi;
	private $description;
	private $mainImageURL;
	private $model;
	private $manufacturer;
	private $seo;
	private $catalog = array();
	private $comp_small = array();
	private $comp_medium = array();
	private $comp_big = array();
	private $foto_small = array();
	private $foto_medium = array();
	private $foto_big = array();
	private $data_small = array();
	private $data_medium = array();
	private $data_big = array();
	
	
	public function __construct( $productID, $productName, $productURL, $memoSmall, $price, $priceMin, $priceMiddle, $priceMaxi, $mainImageURL, $model ) {
		
		$this->productID = $productID;
		$this->productURL = $productURL;
		$this->memoSmall = $memoSmall;
		$this->productName = trim($productName);
		$this->price = $price;
		$this->priceMin = $priceMin;
		$this->priceMiddle = $priceMiddle;
		$this->priceMaxi = $priceMaxi;
		$this->mainImageURL = $mainImageURL;
		$this->model = $model;
		
	}
	
	public function get_ID () {
		return $this->productID;
	}

	public function get_URL () {
		return $this->site . $this->productURL;
	}

	public function get_Name () {
		return $this->productName;
	}

	public function get_memoSmall () {
		return $this->memoSmall;
	}

	public function get_priceMin () {
		return $this->priceMin;
	}

	public function get_priceMiddle () {
		return $this->priceMiddle;
	}

	public function get_priceMaxi () {
		return $this->priceMaxi;
	}

	public function get_mainImageURL () {
		return $this->mainImageURL;
	}
	public function set_mainImageURL ( $URL ) {
		$this->mainImageURL = $URL;
	}
	
	public function get_model () {
		return $this->model;
	}
	
	public function get_Price () {
		return $this->price;
	}
	
	public function get_SEO () {
		return $this->seo;
	}
	public function set_SEO ( $seo ) {
		$this->seo = remove_simvol($seo);
	}
	
	public function set_description ( $description ) {
		$this->description = $description;
	}
	public function get_description () {
		return $this->description;
	}

	public function set_catalog ( $catalog ) {
		if ($catalog) {
			if ( !in_array( $catalog, $this->catalog ) ) {
				$this->catalog[] = $catalog;
			}
		}
	}
	public function get_catalog () {
		return $this->catalog;
	}

	public function set_comp_small ( $title, $ammount ) {
		$this->comp_small[] = array ( '0' => mb_ucfirst(trim($title)), '1' => trim($ammount) );
	}
	public function get_comp_small () {
		return $this->comp_small;
	}

	public function set_comp_medium ( $title, $ammount ) {
		$this->comp_medium[] = array ( '0' => mb_ucfirst(trim($title)), '1' => trim($ammount) );
	}
	public function get_comp_medium () {
		return $this->comp_medium;
	}

	public function set_comp_big ( $title, $ammount ) {
		$this->comp_big[] = array ( '0' => mb_ucfirst(trim($title))	, '1' => trim($ammount) );
	}
	public function get_comp_big () {
		return $this->comp_big;
	}

	public function set_foto_small ( $foto_small ) {
		$this->foto_small[] = $foto_small;
	}
	public function get_foto_small () {
		return $this->foto_small;
	}

	public function set_foto_medium ( $foto_medium ) {
		$this->foto_medium[] = $foto_medium;
	}
	public function get_foto_medium () {
		return $this->foto_medium;
	}
	public function set_foto_big ( $foto_big ) {
		$this->foto_big[] = $foto_big;
	}
	public function get_foto_big () {
		return $this->foto_big;
	}
	
	public function set_data_small ( $data, $price ) {
		$this->data_small[] = array ( 'data' => trim($data), 'price' => trim($price) );
	}
	public function get_data_small () {
		return $this->data_small;
	}

	public function set_data_medium ( $data, $price ) {
		$this->data_medium[] = array ( 'data' => trim($data), 'price' => trim($price) );
	}
	public function get_data_medium () {
		return $this->data_medium;
	}
	public function set_data_big ( $data, $price ) {
		$this->data_big[] = array ( 'data' => trim($data), 'price' => trim($price) );
	}
	public function get_data_big () {
		return $this->data_big;
	}
	
	public function set_manufacturer ( $manufacturer ) {
		$this->manufacturer = $manufacturer;
	}
	public function get_manufacturer () {
		return $this->manufacturer;
	}
	
	
	
	
}


function get_num_catalog ( $name ) {
	
		global $catalog;

		if ( trim($name)) { 
			$value = trim($name);
			for($i=0;$i<count($catalog);$i++){
				$key = array_search($value,$catalog[$i]);
					if ($key) { 
						return $catalog[$i][0]; 
					}
			}
		} else {
			return 15;
		}
	//return 0;
}


// Функция синхронизации каталогов в продуктах
function catalog_sync( $prod_arr ) {
	
	global $catalog;
	
	for($i=0;$i<count($catalog);$i++) {
		
		if ( !empty($catalog[$i][2]) ) {
			
			logger( "Проверяем каталог: " . $catalog[$i][1] );
			//ob_flush();
			//flush();
	
			$html = curl_get($catalog[$i][2]);
			$html_base = new simple_html_dom();
			$html_base->load($html);
			
			$flovers = $html_base->find('.b-item-inner');
			
			// Инициализация массива товаров (заполняем с главной страницы)
			foreach($flovers as $flover){	

				$productID = $flover->find('a.b-small-order-btn',0)->attr['data-nodeid'];
				
				for ( $k=0;$k<count($prod_arr);$k++ ) {
			
					if ( $productID == $prod_arr[$k]->get_ID() ) {
						
						$prod_arr[$k]->set_catalog( $catalog[$i][0] );
						
						//print "ID:" . $prod_arr[$k]->get_ID() ." NEW CATALOG: " . $catalog[$i][0] . "<br>";
						//ob_flush();
						//flush();
	
						break;
						
					}
				
				}

		
			}
		}
	}	
	
	
}
 
function mb_ucfirst($str, $encoding='UTF-8')
    {
        $str = mb_ereg_replace('^[\ ]+', '', $str);
        $str = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding).
               mb_substr($str, 1, mb_strlen($str), $encoding);
        return $str;
    }

// Функция фырезания символаов "-" с конца строки
function remove_simvol( $str ) {
	
	while (  strrpos($str,"-") == strlen($str)-1  ) {
	
			if ( strrpos($str,"-") == strlen($str)-1  ) {
				
				$str = substr_replace( $str, "", -1 );
				
			}
	}
	
	return $str;
	
}

function logger($text) {
	file_put_contents("log_new.txt", date("Y-m-d H:i:s - ").$text."\n", FILE_APPEND);
}

//*******************************************************************************************************
//Функция скачивания файла изображения
//*******************************************************************************************************		

function save_img ($img_url) {		
		
		global $save_dir;
		global $img_download;
		
		$image_file = $save_dir . basename($img_url);
		
		if ($img_download and !file_exists($image_file)) {
		
			
			$img_file = curl_get($img_url);
			
			
			if (!file_exists($image_file)) {
				file_put_contents($save_dir . basename($img_url), $img_file);
			}
		}
		
		return basename($img_url);
		
}

//***********************************************************************************************


function NormalizeString( $string )
{
	static $lang2tr = array(
		// russian
		'й'=>'j','ц'=>'c','у'=>'u','к'=>'k','е'=>'e','н'=>'n','г'=>'g','ш'=>'sh',
		'щ'=>'sh','з'=>'z','х'=>'h','ъ'=>'','ф'=>'f','ы'=>'y','в'=>'v','а'=>'a',
		'п'=>'p','р'=>'r','о'=>'o','л'=>'l','д'=>'d','ж'=>'zh','э'=>'e','я'=>'ja',
		'ч'=>'ch','с'=>'s','м'=>'m','и'=>'i','т'=>'t','ь'=>'','б'=>'b','ю'=>'ju','ё'=>'e','и'=>'i',

		'Й'=>'J','Ц'=>'C','У'=>'U','К'=>'K','Е'=>'E','Н'=>'N','Г'=>'G','Ш'=>'SH',
		'Щ'=>'SH','З'=>'Z','Х'=>'H','Ъ'=>'','Ф'=>'F','Ы'=>'Y','В'=>'V','А'=>'A',
		'П'=>'P','Р'=>'R','О'=>'O','Л'=>'L','Д'=>'D','Ж'=>'ZH','Э'=>'E','Я'=>'JA',
		'Ч'=>'CH','С'=>'S','М'=>'M','И'=>'I','Т'=>'T','Ь'=>'','Б'=>'B','Ю'=>'JU','Ё'=>'E','И'=>'I',
		
		// special
		' '=>'_', '-'=>'_', '\''=>'', '"'=>'', '\t'=>'', '«'=>'', '»'=>'', '?'=>'', '!'=>'', '*'=>''
	);
	$url = preg_replace( '/[\-]+/', '-', preg_replace( '/[^\w\-\*]/', '', strtolower( strtr( $string, $lang2tr ) ) ) );
	//echo $url."<br>";
	return  $url;
}
?>