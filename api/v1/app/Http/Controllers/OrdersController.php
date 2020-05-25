<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SendGrid\Mail\Mail;

class OrdersController extends Controller
{
    public function createOrder(Request $request) {
        $data = $request->input();          
        $orderCreated = $this->saveCustomerInfo($data['customerInfo'],$data['orderItems'], 
        $data['orderID'], $data['total'],
    $data['orderNote'], $data['shippingAddress']);
        if($orderCreated) {
            $response = $this->sendOrderEmail($data['customerInfo']['email'], 
            'transaction@ebaaba.xyz',
            $data['customerInfo']['fullName'],
            $data['orderID']);
            return response()->json(['Created' => $orderCreated, 'EmailSentStatus' => $response]);
        }
        return response()->json(['Created' => $orderCreated]);
    }

    private function saveCustomerInfo(array $customerInfo, array $order_items,
     string $order_id, string $total, string $order_note, string $shipping_address) {
        $id = DB::table('customers')
        ->insertGetId(
            ['full_name' => $customerInfo['fullName'],
             'address' => $customerInfo['address'],
             'country' => $customerInfo['country'],
             'town_city' => $customerInfo['townCity'],
             'phone_number' => $customerInfo['phone'],
             'email' => $customerInfo['email']
        ]
        );

        if(is_numeric($id)) {
            $orderCreated = $this->saveOrderInfo($order_items, $order_id, $id, $total, $order_note, $shipping_address);
            return $orderCreated;
        }


    }

    private function saveOrderInfo(array $order_items, string $order_id, 
    int $customer_id, string $total, string $order_note, string $shipping_address) {
        $order_created = DB::table('orders')->insert(
            ['products' => json_encode($order_items),
            'order_number' => $order_id,
            'order_status' => 'Processing',
            'order_note' => $order_note,
            'shipping_address' => $shipping_address,
            'total' => $total,
            'customer_id' => $customer_id
            ]);
        return $order_created;
    }

    private function sendOrderEmail(string $to_email, 
    string $from_email, string $username,
    string $order_id) {
        $email = new Mail(); 
        $email->setFrom($from_email, "eBaaba");
        $email->setSubject("Order Confirmation");
        $email->addTo($to_email, $username);
        // $email->addContent("text/plain", "and easy to do anywhere, even with PHP");
        $email->addContent(
            "text/html", "<strong>Order confirmation email, your order id is $order_id</strong>"
        );
        $sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));
        try {
            $response = $sendgrid->send($email);
            return response()->json(['StatusCode' => $response->statusCode()]);
        } catch (Exception $e) {
            return response()->json(['Error' => 'Caught exception: '. $e->getMessage()]);
        }
    }

    public function getAllOrders() {
        $all_orders = DB::table('orders')
        ->join('customers','customers.id', 'orders.customer_id')
        ->orderBy('orders.created_at', 'desc')
        ->paginate(7);

        return response()->json($all_orders);
    }

    /**
     * @return json - total number of orders
     */
    public function totalOrders() {
        $total_orders = DB::table('orders')->get();
        return response()->json(['Total' => count($total_orders)]);
    }

    /**
     * @return json - number of orders made the current day
     */
    public function getTodayOrders() {
        $total_orders = DB::table('orders')
        ->select(DB::raw('*'))
        ->whereRaw('Date(created_at) = CURDATE()')->get();

        return response()->json(['Today' => count($total_orders)]);
    }

    /**
     * @return json - total number of pending orders
     */
    public function getTotalPendingOrders() {
        $pending_orders = DB::table('orders')
        ->select(DB::raw('*'))
        ->where('order_status', 'Processing')->get();

        return response()->json(['Pending' => count($pending_orders)]);
    }

    public function getCompletedOrders() {
        $completed_orders = DB::table('orders')
        ->select(DB::raw('*'))
        ->where('order_status', 'Completed')
        ->get();

        return response()->json(['Completed' => count($completed_orders)]);
    }

    public function completeOrder(Request $request) {
        $data = $request->input();

        $updatedOrder = DB::table('orders')
        ->where('order_number', $data['order_number'])
        ->update(['order_status' => 'Completed']);

        if($updatedOrder) {
            return response()->json(['Updated' => true]);
        }
        return response()->json(['Updated' => false]);
    }

    /**
     * @return json - single order
     */
    public function getOrder($order_id) {
        $order = DB::table('orders')
        ->where('orders.order_number', $order_id)
        ->join('customers','customers.id', 'orders.customer_id')
        ->get();

        return response()->json($order);
    }

    public function deleteOrder(Request $request) {
        $deleted_order = DB::table('orders')
        ->where('order_number', $request->input('orderID'))
        ->delete();

        if($deleted_order === 1) {
            return response()->json(['OrderDeleted' => true]);
        }

        return response()->json(['OrderDeleted' => false]);
    }
}
