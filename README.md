<?php

public function get_product_statistics($product_id, $date_begin, $date_end, $collection = '')
{
    $condition = '';
    
    if($collection){
        $condition = 'AND tblProduct.ProductCollection = '.$collection;
    }
    
    $query = $this->db->query("
        SELECT 
            prod.*, shop.*,
            SUBSTR(price.PricesDate, 1, 10) AS price_date,
            price.ProductGUID,
            cat.ProductCategoryName AS category,
            gr.ProductGroupName AS group,
            subcat.ProductSubcategoryName AS subcategory,
            AVG(price.PricesPriceNetto) AS netto_price, 
            AVG(price.PricesPriceBruto) AS bruto_price, 
            AVG(price.PricesDelivery) AS delivery_price
        FROM 
            tblPrices price       
            JOIN tblProduct prod USING(ProductGUID)
            JOIN tblWebshop shop ON shop.WebshopGUID = price.PricesWebshopGUID
            JOIN tblProductCategory cat USING (ProductCategoryGUID)
            JOIN tblProductGroup gr USING (ProductGroupGUID)
            JOIN tblProductSubcategory subcat ON subcat.ProductSubcategoryGUID = prod.ProductSubcategoryGUID
        WHERE 
            prod.ProductGUID = $product_id
            AND SUBSTR(price.PricesDate, 1, 10) BETWEEN '$date_begin' AND '$date_end'
            $condition
        GROUP BY shop.WebshopGUID
        ORDER BY shop.WebshopName
    ");
    
    return $query;
}
