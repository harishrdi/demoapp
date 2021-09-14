<?php
namespace App\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class OrderController
{
    const DISCOUNT_TYPE_PERCENTAGE = 'PERCENTAGE';
    const DISCOUNT_TYPE_DOLLAR = 'DOLLAR';


    /**
     * Method to get the details from path and convert to csv.
     *
     * @return Response
     */
    public function orderReport(): Response
    {
        $path = 'https://s3-ap-southeast-2.amazonaws.com/catch-code-challenge/challenge-1/orders.jsonl';
        $data = file_get_contents($path);
        $convertedJsonArray = explode("\n", $data);
        $arrayList = [];
        $arrayList[0] = ['OrderId', 'OrderDateTime', 'totalOrderValue', 'averageUnitPrice', 'distinctUnitCount', 'totalUnitsCount', 'customerState' ];
        foreach ($convertedJsonArray as $key => $jsonValue) {
            $temp = json_decode($jsonValue, true);
            if (isset($temp['order_id'])) {
                $orderTime = trim($temp['order_date'], '"');
                $shippingCode = trim($temp['customer']['shipping_address']['state'], '"');
                $totalOrder = 0;
                $itemCount = count($temp['items']);
                $totalUnitPrice = 0;
                $totalQty = 0;
                foreach ($temp['items'] as $key => $item) {
                    $totalOrder += ($item['unit_price'] * $item['quantity']);
                    $totalUnitPrice += $item['unit_price'];
                    $totalQty += $item['quantity'];
                }
                $totalAvgPrice = round($totalUnitPrice / $itemCount);

                if (isset($temp['discounts']) && !empty($temp['discounts'])) {

                    if (isset($temp['discounts'][0]['type']) && $temp['discounts'][0]['type'] == self::DISCOUNT_TYPE_PERCENTAGE) {
                        $totalDiscount = ($totalOrder / $temp['discounts'][0]['value']) * 100;
                        $totalOrder = round($totalOrder - $totalDiscount);
                    }
                    if (isset($temp['discounts'][0]['type']) && $temp['discounts'][0]['type'] == self::DISCOUNT_TYPE_DOLLAR) {
                        $totalOrder = round($totalOrder - $temp['discounts'][0]['value']);
                    }
                }
                $arrayList[] = [
                    $temp['order_id'],
                    round(strtotime($orderTime)),
                    $totalOrder,
                    $totalAvgPrice,
                    $itemCount,
                    $totalQty,
                    (string)str_replace('"', ' ',round($shippingCode)) //yet to show.
                ];
            }
        }
        $fp = fopen('php://output', 'wb');
        foreach ($arrayList as $fields) {
            fputcsv($fp, $fields);
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="test.csv"');
        return $response;
    }
}