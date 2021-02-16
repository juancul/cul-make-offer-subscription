<?php
/**
 * Make an offer for a subscription. Subscrition info in admin
 *
 * @package cul-make-offer-subscription
 *
 * Plugin Name:       CUL - Make an offer for a subscription. Subscrition info in admin
 * Description:       Plugin that adds  a maken an offer button to subscription and adds subscription information to the woocommerce offer edit page
 * Version:           1.1
 * Author:            CUL
 */


/*
* Front
* Adds all fields to the make an offer form needed to make an offer for a subscription product
*/
add_action('make_offer_form_before_submit_button', 'add_custom_field_make_offer_form', 10);
add_action('make_offer_after_save_form_data', 'save_custom_field_make_offer_form', 10, 2);
add_action('make_offer_after_buyer_meta_display', 'display_custom_field_buyer_section');
add_action('make_offer_email_display_custom_field_after_buyer_contact_details', 'display_custom_field_after_buyer_contact_details', 10, 1);

function add_custom_field_make_offer_form() {
    ?>
    <div class="woocommerce-make-offer-form-section">
        <label for="offer_postcode"><?php echo __('Alquiler: '.$_POST["subscription-id"], 'offers-for-woocommerce'); ?></label>
        <br><input type="hidden" value="<?php echo $_POST['subscription-id'];?>" required="required" name="offer_subscription_id" id="offer_subscription_id">
        <style>
            .single_add_to_cart_button{
                display:none!important;
            }
        </style>
    </div>
    <?php
}

function save_custom_field_make_offer_form($post_id, $post) {
    if (isset($post['offer_subscription_id']) && !empty($post['offer_subscription_id'])) {
        add_post_meta($post_id, 'offer_subscription_id', $post['offer_subscription_id']);
    }
}

function display_custom_field_buyer_section($post_id) {
    $offer_subscription_id = get_post_meta($post_id, 'offer_subscription_id', true);
    ?>
    <li><span><?php echo __('Alquiler:', 'offers-for-woocommerce'); ?>&nbsp;</span>
        <?php echo (isset($offer_subscription_id)) ?
            stripslashes($offer_subscription_id) : __('Missing Meta Value', 'offers-for-woocommerce'); ?>
    </li>
    <?php
}

function display_custom_field_after_buyer_contact_details($post_id) {
    $offer_subscription_id = get_post_meta($post_id, 'offer_subscription_id', true);
    echo (isset($offer_subscription_id) && !empty($offer_subscription_id)) ?
        '<br /><strong>' . __('Alquiler:', 'offers-for-woocommerce') . '&nbsp;</strong>' . stripslashes($offer_subscription_id) : '';
}

/*
* Admin
* Add meta box with the rental information so a decision on the offer can be made in the offer details
*/
add_action( 'add_meta_boxes', 'offers_rental_data_box' );
function offers_rental_data_box() {
    add_meta_box(
        'offers-meta-box',
        'Información Alquiler',
        'offer_meta_box_callback',
        'woocommerce_offer',
        'side',
        'core'
    );
}

function offer_meta_box_callback( $post )
{

        $offer_subscription_id = get_post_meta($post->ID, 'offer_subscription_id', true);
        $sub_user_email =get_post_meta( $post->ID, 'offer_email', true );
        $sub_user = get_user_by( 'email', $sub_user_email );
        $sub_user_id = $sub_user->ID;
    if($offer_subscription_id){
        $subscription = wcs_get_subscription($offer_subscription_id);
        $subscription_length = wcs_estimate_periods_between( $subscription->get_time( 'start' ), $subscription->get_time( 'end' ), $subscription->get_billing_period() );
        $subscription_payments = $subscription->get_completed_payment_count();
        $subscription_related_orders = $subscription->get_related_orders();
        $recurring_total = $subscription->get_total();

        //Get completed order count for subscription
        $subscription_completed_order_count = 0;
        $subscription_pending_order_count = 0;
        $pending_total = 0;
        foreach ($subscription_related_orders as $order_id){
            $order = wc_get_order( $order_id );
            
            if ( $order->has_status('completed') ) {
            $subscription_completed_order_count += 1;
            }
            if ( $order->has_status('pending') ) {
            $subscription_pending_order_count += 1;
            $pending_total += $order->get_total();
            }
        }

        //Start Meta Box Information 
        echo 'Pagos hechos en el plan: ' . $subscription_completed_order_count;
        if ($subscription_pending_order_count > 0){
            echo '<br><br><span style ="background-color: #f54b42; color: #ffffff; padding: 3px;border-radius: 2px;">Pagos pendientes en el plan: ' . $subscription_pending_order_count . '</span>';
            echo '<br><br><span style ="background-color: #f54b42; color: #ffffff; padding: 3px;border-radius: 2px;">Valor pendiente: ' . wc_price($pending_total) . '</span>';
        }
        echo '<br><br>Pagos plan total: ' . $subscription_length;
        echo '<br><br>Pagos restantes en plan: ' . ($subscription_length-$subscription_completed_order_count); 
        echo '<br><br>Valor restante en plan: ' . wc_price(($subscription_length-$subscription_completed_order_count)*$recurring_total);
        echo '<br><br>Oferta Aceptable: ' . wc_price((($subscription_length-$subscription_completed_order_count)*$recurring_total)*.9);
        echo '<br><br>Ver Alquiler: <a href ="https://vivecul.com.co/wp-admin/post.php?post='.$offer_subscription_id.'&action=edit" target="_blank">'.$offer_subscription_id.'</a>';
        echo '<br><br><a href ="https://vivecul.com.co/wp-admin/edit.php?s=oferta+por+alquiler&post_status=all&post_type=shop_order&_customer_user='.$sub_user_id.'" target="_blank">Ver Pedidos de Oferta</a>';
    }
    else {
       echo 'No relacionó alquiler<br> <a href ="https://vivecul.com.co/wp-admin/edit.php?post_type=shop_subscription&_customer_user='.$sub_user_id.'" target="_blank"> Ver Alquileres</a>';
    }
}


/*
* Front
* Display Make an Offer button in subscription detail page
* Display Information as to when the offer can be placed and if it can be placed
*/
add_action('woocommerce_subscription_details_table', 'add_offer_button');

function add_offer_button($subscription) {           
    $subscription_related_orders = $subscription->get_related_orders();
    $subscription_related_resubscribe = $subscription->get_related_orders('all','resubscribe');
    $recurring_total = $subscription->get_total();
    $subscription_completed_order_count = 0;
    $subscription_pending_order_count = 0;
    $pending_total = 0;
    $subscription_length = wcs_estimate_periods_between( $subscription->get_time( 'start' ), $subscription->get_time( 'end' ), $subscription->get_billing_period() );
    foreach ($subscription_related_orders as $order_id){
        $order = wc_get_order( $order_id );
        
        if ( $order->has_status('completed') ) {
        $subscription_completed_order_count += 1;
        }
        if ( $order->has_status('pending') ) {
        $subscription_pending_order_count += 1;
        $pending_total += $order->get_total();
        }
    }

    //Gets the months needed to make an offer for each subscription plan
    if ($subscription_length <= 6){
        $subscription_length_for_offer=12;
    }
    else if ($subscription_length <= 9 && $subscription_length > 6  ){
        $subscription_length_for_offer=14;
    }
    else if ($subscription_length <= 12 && $subscription_length > 9  ){
        $subscription_length_for_offer=16;
    }

    else if ($subscription_length >= 18) {
        $subscription_length_for_offer=18;
    }
    //Only displays for subsritions above 75903 when the change in pricing plans was made
    if ($subscription->get_id()>75903){
        //Displays message within parent subscription when a resubscription already exists
        if (get_post_meta( $subscription->get_id(), '_subscription_resubscribe_order_ids_cache', true )){
            foreach ($subscription_related_resubscribe as $order_id){
                if (get_post_type($order_id->id) == "shop_subscription"){
                    $rel_subscription_child = wcs_get_subscription($order_id->id);
                    if(($subscription_completed_order_count+$rel_subscription_child->get_completed_payment_count())==$subscription_length_for_offer) {
                        echo '<p class="woocommerce-info">Si te enamoraste de los productos y crees que es para ti ya puedes hacer una oferta para quedártelos.</p>
                              <form action="/producto/oferta-por-alquiler/" method="post">
                                <input type="hidden" name="subscription-id" value="'.$subscription->get_id().'" />
                                <input type="submit" value="Hacer una oferta" class="button"/>
                              </form><hr class="wp-block-separator"><br><br><br>';
                    }
                    else {
                        echo '<p class="woocommerce-info">Este alquiler es por <strong>'.$subscription_length.' meses</strong>. Si te enamoraste y quieres hacer una oferta para quedarte con los productos de este alquiler debes terminar este plan de <strong>'.$subscription_length.' meses</strong> y luego alquilar por otros <strong>'. ($subscription_length_for_offer - $subscription_length).' meses</strong>.</br>
                        Este es tu resumen:</br>
                        Meses pagados en primer alquiler: '.$subscription_completed_order_count.'</br>
                        Meses pagados en segundo alquiler: '.$rel_subscription_child->get_completed_payment_count().'
                        </p>
                        <hr class="wp-block-separator">';
                    }    
                }
            
            }
        }
        //Displays message within child re-subscription if it exists
        else if ( get_post_meta( $subscription->get_id(), '_subscription_resubscribe', true ) ){
                intval($resubscrptions_id = get_post_meta( $subscription->get_id(), '_subscription_resubscribe', true )).'holi<br>';
                $rel_subsubscription_parent = wcs_get_subscription($resubscrptions_id);
                if(($subscription_completed_order_count+$rel_subsubscription_parent->get_completed_payment_count())==$subscription_length_for_offer) {
                        echo '<p class="woocommerce-info">Ya puedes hacer una oferta si te enamoraste de los productos de este alquiler y crees que son para ti.</p>
                              <form action="/producto/oferta-por-alquiler/" method="post">
                                <input type="hidden" name="subscription-id" value="'.$subscription->get_id().'" />
                                <input type="submit" value="Hacer una oferta" class="button"/>
                              </form><hr class="wp-block-separator"><br><br><br>';
                    }
                else {    
                    echo '<p class="woocommerce-info">Este alquiler es tu segundo alquiler por <strong>'.$subscription_length.' meses</strong>. Si te enamoraste y quieres hacer una oferta para y quedarte con el/los productos debes terminar los meses restantes en este alquiler </br>
                        Este es tu resumen:</br>
                        Meses pagados en primer alquiler: '.$rel_subsubscription_parent->get_completed_payment_count().'</br>
                        Meses pagados en segundo alquiler: '.$subscription_completed_order_count.'
                        </p>
                        <hr class="wp-block-separator">';
                }
        }
        //Displays message within unique subscription if max plan is selected (18 months)
        else if ($subscription_length >= 18){
            if($subscription_completed_order_count>=$subscription_length_for_offer) {
                echo '<p class="woocommerce-info">Ya puedes hacer una oferta si te enamoraste de los productos de este alquiler y crees que son para ti.</p>
                      <form action="/producto/oferta-por-alquiler/" method="post">
                        <input type="hidden" name="subscription-id" value="'.$subscription->get_id().'" />
                        <input type="submit" value="Hacer una oferta" class="button"/>
                      </form><hr class="wp-block-separator"><br><br><br>';
            }
            else {
                echo '<p class="woocommerce-info">Este alquiler es por <strong>'.$subscription_length.' meses</strong>. Si te enamoraste y quieres hacer una oferta para quedarte con los productos de este alquiler debes terminar este plan de <strong>'.$subscription_length.' meses</strong></br>
                        Este es tu resumen:</br>
                        Meses pagados en alquiler: '.$subscription_completed_order_count.'</p>
                    <hr class="wp-block-separator">';
            }
        } 
        //Displays message within unique subscription that needs a resubscription to make an offer but still does not have one
        else {
            echo '<p class="woocommerce-info">Este alquiler es por <strong>'.$subscription_length.' meses</strong>. Si te enamoraste y quieres hacer una oferta para quedarte con los productos de este alquiler debes terminar este plan de <strong>'.$subscription_length.' meses</strong>  y luego alquilar por otros <strong>'. ($subscription_length_for_offer - $subscription_length).' meses</strong>.</br>
                ¡No te preocupes te saldrá un botón para "Re-Alquilar" y otro para "Hacer una Oferta" en su debido momento!</p>
                <hr class="wp-block-separator">';
        }     
    }
    //Displays message within unique subscriptions before plans change implementation below subscrition 70000
    else {
        if($subscription_completed_order_count>=$subscription_length) {
                echo '<p class="woocommerce-info">Ya puedes hacer una oferta si te enamoraste de los productos de este alquiler y crees que son para ti.</p>
                      <form action="/producto/oferta-por-alquiler/" method="post">
                        <input type="hidden" name="subscription-id" value="'.$subscription->get_id().'" />
                        <input type="submit" value="Hacer una oferta" class="button"/>
                      </form><hr class="wp-block-separator"><br><br><br>';
        }
        else {
            echo '<p class="woocommerce-info">Este alquiler es por <strong>'.$subscription_length.' meses</strong>. Si te enamoraste y quieres hacer una oferta para quedarte con los productos de este alquiler debes terminar este plan de <strong>'.$subscription_length.' meses</strong></br>
                    Este es tu resumen:</br>
                    Meses pagados en alquiler: '.$subscription_completed_order_count.'</p>
                <hr class="wp-block-separator">';
        }
    }
} 

/**
* Adds messages in checkout depending on the selected subscrition plan
*
**/

// This function returns the smallest amount of months one of the plans has in the cart
function cul_find_plan_duration_in_cart_subs() {

    $products = WC()->cart->cart_contents;
    $all_variation_titles = '';
    foreach ($products as $product) {
        $variation_id = $product['variation_id'].'<br>';
        $all_variation_titles .= get_the_title($variation_id);

        if (strpos($all_variation_titles, '6 Meses') !== false) {
            return 6;
        }
        else if (strpos($all_variation_titles, '9 Meses') !== false) {
            return 9;
        }
        else if (strpos($all_variation_titles, '12 Meses') !== false) {
            return 12;
        }
        else if (strpos($all_variation_titles, '18 Meses') !== false) {
            return 18;
        }

        else {
          return false;
        }
        
    }
  
}

//This function finds a subscription product depending on the months in the cart and displays the message depending on the plan
add_filter('woocommerce_before_checkout_form', 'custom_subscription_checkout_message');

function custom_subscription_checkout_message() {
    $cart_data = WC()->session->get('cart');
    $cart = $cart_data[array_key_first($cart_data)];
    if(isset($cart['subscription_renewal']) == false && isset($cart['subscription_renewal']['subscription_id']) == false ) {
        //Show a message depending of the smallest plan in the cart
        if (cul_find_plan_duration_in_cart_subs() == 6){
            echo '<div class="woocommerce-info cart-rental-message">
                    <span class="cart-notice">Este alquiler es un compromiso por 6 meses. Solo podrás hacer una oferta para quedarte con los productos si vuelves a alquilar por otros 6 meses una vez termines este plan</span>
              </div>';
        }
        else if (cul_find_plan_duration_in_cart_subs() == 9){
            echo '<div class="woocommerce-info cart-rental-message">
                    <span class="cart-notice">Este alquiler es un compromiso por 9 meses. Solo podrás hacer una oferta para quedarte con los productos si vuelves a alquilar por otros 5 meses una vez termines este plan</span>
              </div>';
        }
        else if (cul_find_plan_duration_in_cart_subs() == 12){
            echo '<div class="woocommerce-info cart-rental-message">
                    <span class="cart-notice">Este alquiler es un compromiso por 12 meses. Solo podrás hacer una oferta para quedarte con los productos si vuelves a alquilar por otros 4 meses una vez termines este plan</span>
              </div>';
        }
        else if (cul_find_plan_duration_in_cart_subs() == 18){
            echo '<div class="woocommerce-info cart-rental-message">
                    <span class="cart-notice">Este alquiler es un compromiso por 18 meses. Solo podrás hacer una oferta para quedarte con los productos una vez termines este plan</span>
              </div>';
        }
        
    }
}