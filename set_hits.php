#!/usr/bin/php
<?

// 



	$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

	define("NO_KEEP_STATISTIC", true);
	define("NOT_CHECK_PERMISSIONS", true);

	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
	set_time_limit(0);

	// ******************************************************************************
	
	$CATALOGS_ARR 	= array (117,118);	// ID Категорий товара
	$PRD_AMOUNT		= 12;				// Кол-во товара для выборки

	// ******************************************************************************


	$IBLOCK_ID	 = 13;					// IBLOCK - товара

	if (CModule::IncludeModule("iblock")):
	
			logger("******************************************************");
			// *********************************************
			// Очищаем признаки 
			// *********************************************
			$arSelect = Array("ID", "NAME");
			$arFilter = Array("IBLOCK_ID"=>$IBLOCK_ID);
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

			while($ob = $res->GetNext())
			{
			 
			 $PRODUCT_ID = $ob['ID'];
			  
				 if (get_id_prop ($PRODUCT_ID,$IBLOCK_ID)) {
					
					del_element_prop ($PRODUCT_ID, $IBLOCK_ID);
					
				 }
			  
			}
	
		// *********************************************
		// Добавляем товарам признаки 
		// *********************************************
	
		$prd_count = 0;
		$array_id = array();

		while ( $prd_count <= $PRD_AMOUNT ) {
		
			for ($j=0;$j<count($CATALOGS_ARR);$j++) {
				
				$SECTION_ID = $CATALOGS_ARR[$j];

				$arSelect = Array("ID", "IBLOCK_ID", "NAME");
				
				$arFilter = Array("IBLOCK_ID"=>$IBLOCK_ID, "SECTION_ID"=>$SECTION_ID);
				
				$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
				
				$arr_in = $res->arResult;
				
				$rand_end = count($arr_in) - 1;
				
				if ( count($arr_in) < $PRD_AMOUNT ) {					
					$PRD_AMOUNT = count($arr_in);
				}

				$rand_id = rand(0,$rand_end);
				
				$PRODUCT_ID = $arr_in[$rand_id]['ID'];
				
				
				if (  $PRODUCT_ID ) {

					if ( $prd_count < $PRD_AMOUNT ) {
						
						// Проверка на повторные попадания
						if ( !in_array( $PRODUCT_ID, $array_id ) ) {

							logger("Добавляем признак ХИТ: " . $PRODUCT_ID . " " . $arr_in[$rand_id]['NAME']);
							//add_element_prop( $PRODUCT_ID, $IBLOCK_ID, "Новинка", "7" );
							add_element_prop( $PRODUCT_ID, $IBLOCK_ID, "Хит продаж", "8" );

							$prd_count++;	
							$array_id[] = $PRODUCT_ID;
						}
					} else {
						exit;
					}

					
				}	
			
			}
		}
		
		
	
	
	endif;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");



// ************************************************************************************
// Функция определения цены. Таблица: b_catalog_price
// ************************************************************************************	
function get_price( $PRODUCT_ID ) {
		
		
$PRICE_TYPE_ID  = 1;


if (CModule::IncludeModule("iblock")):


		$db_res = CPrice::GetList(
				array(),
				array(
						"PRODUCT_ID" 		=> $PRODUCT_ID,
						"CATALOG_GROUP_ID" 	=> $PRICE_TYPE_ID
					)
			);
		if ($ar_res = $db_res->Fetch())
		{
			//echo CurrencyFormat($ar_res["PRICE"], $ar_res["CURRENCY"]);
			return $ar_res["PRICE"];
		}
		else
		{
			return false;
		}
endif;

}

function get_id_prop ($PRODUCT_ID,$IBLOCK_ID) {
	
	$flag = false;
	
	if (CModule::IncludeModule("iblock")):
	
	$el = new CIBlockElement;
	
			$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array());
					
					while ( $ob = $res->GetNext() ) {
						if (  $ob['VALUE'] ) {
							
							if (  $ob['CODE'] == 'NEWPRODUCT' or $ob['CODE'] == 'SALELEADER' ) {
								//print $ob['CODE'] . " -> " . $ob['VALUE'] . "<br>";
								$flag = true;
							}
							
						}
					}
					

endif;

return $flag;

}

// ************************************************************************************
// Функция удалениЯ значениЯ атрибута. Таблица: b_iblock_element_property
// ************************************************************************************
function del_element_prop( $PRODUCT_ID, $IBLOCK_ID ) {
	
	
	
	if (CModule::IncludeModule("iblock")):
	
	$el = new CIBlockElement;
	
			$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array("sort" => "asc"));
					
					while ( $ob = $res->GetNext() ) {
						if (  $ob['VALUE'] ) {
							
							if (  $ob['CODE'] != 'NEWPRODUCT' and $ob['CODE'] != 'SALELEADER' ) {
								$PROP[$ob['CODE']] = $ob['VALUE'];
								//print $ob['CODE'] . " -> " . $ob['VALUE'] . "<br>";
							}
							
						}
					}
					
					$arLoadProductArray = Array("PROPERTY_VALUES"=> $PROP); 
					if ($el->Update($PRODUCT_ID, $arLoadProductArray) ) {
						logger("Очищаем признаки ХИТ для: " . $PRODUCT_ID );
						return true;
					} else {
						//logger( "ERROR ADD PROPERTY: " . $el->LAST_ERROR );
						return false;
					}

endif;

}
	

// ************************************************************************************
// 
// ************************************************************************************
function add_element_prop( $PRODUCT_ID, $IBLOCK_ID, $ATTR_NAME, $ATTR_VALUE ) {
	
	
if (CModule::IncludeModule("iblock")):

					$el = new CIBlockElement;
		
					$ATTR_ID = check_prop( $IBLOCK_ID, $ATTR_NAME );
		
					//
					$PROP = Array(); 

					$res = $el->GetProperty($IBLOCK_ID,$PRODUCT_ID,array("sort" => "asc"));
					
					while ( $ob = $res->GetNext() ) {
						if (  $ob['VALUE'] ) {
							
							$PROP[$ob['CODE']] = $ob['VALUE'];
							
							//print $ob['CODE'] . " -> " . $ob['VALUE'] . "<br>";
						}
					}
					
					$PROP[$ATTR_ID] = array("VALUE" => $ATTR_VALUE, "VALUE_ENUM" => $ATTR_VALUE);
				
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
// ”ункциЯ проверки наличиЯ Ђтрибута. ’аблица: b_iblock_property
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

function logger($text) {
	file_put_contents("log_set_hits.txt", date("Y-m-d H:i:s - ").$text."\n", FILE_APPEND);
}

?>