<?php
/**
 * Order Handler for WooPoints
 *
 * Hooks into WooCommerce order lifecycle to award
 * points when an order is marked as completed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_Order_Handler {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Award points when order status changes to completed
        add_action( 'woocommerce_order_status_completed', array( $this, 'award_points_on_complete' ), 10, 1 );

        // Also support processing status for digital goods
        add_action( 'woocommerce_order_status_processing', array( $this, 'award_points_on_processing' ), 10, 1 );

        // Deduct points if order is refunded
        add_action( 'woocommerce_order_status_refunded', array( $this, 'deduct_points_on_refund' ), 10, 1 );

        // Display points earned on order details / thank you page
        add_action( 'woocommerce_thankyou', array( $this, 'display_points_on_thankyou' ), 5, 1 );

        // Show points info on order received emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_points_in_email' ), 10, 4 );

        // Show points preview on cart and checkout pages
        add_action( 'woocommerce_before_checkout_form', array( $this, 'display_checkout_points_preview' ), 5 );
        add_action( 'woocommerce_before_cart_table', array( $this, 'display_cart_points_preview' ), 5 );
        add_action( 'woocommerce_review_order_before_submit', array( $this, 'display_checkout_points_inline' ), 10 );
    }

    /**
     * Award points when order is completed
     */
    public function award_points_on_complete( $order_id ) {
        $this->process_points_for_order( $order_id );
    }

    /**
     * Award points when order is processing (for digital goods)
     */
    public function award_points_on_processing( $order_id ) {
        // Only auto-award on processing if the setting is enabled
        $auto_award = get_option( 'wpr_award_on_processing', 'no' );
        if ( $auto_award === 'yes' ) {
            $this->process_points_for_order( $order_id );
        }
    }

    /**
     * Process and award points for an order
     */
    private function process_points_for_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check if points have already been awarded for this order
        $already_awarded = $order->get_meta( '_wpr_points_awarded', true );
        if ( $already_awarded === 'yes' ) {
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return; // Guest orders are not eligible
        }

        // Get the order total (excluding tax and shipping optionally)
        $order_total = $this->get_eligible_total( $order );

        // Calculate points (1 currency unit = rate * points)
        $rate   = floatval( get_option( 'wpr_points_rate', 1 ) );
        $points = floor( $order_total * $rate );

        if ( $points <= 0 ) {
            return;
        }

        // Award points
        $description = sprintf(
            __( 'Points earned from Order #%s (Total: %s)', 'woo-points-rewards' ),
            $order->get_order_number(),
            wc_price( $order_total )
        );

        $awarded = WPR_Points_Manager::add_points( $user_id, $points, 'purchase', $order_id, $description );

        if ( $awarded ) {
            // Mark order as points awarded
            $order->update_meta_data( '_wpr_points_awarded', 'yes' );
            $order->update_meta_data( '_wpr_points_earned', $points );
            $order->save();

            // Add order note
            $order->add_order_note(
                sprintf(
                    __( 'WooPoints: %s points awarded to customer.', 'woo-points-rewards' ),
                    number_format( $points )
                )
            );
        }
    }

    /**
     * Get the eligible order total for points calculation
     */
    private function get_eligible_total( $order ) {
        $include_tax      = get_option( 'wpr_include_tax', 'no' ) === 'yes';
        $include_shipping = get_option( 'wpr_include_shipping', 'no' ) === 'yes';

        $total = floatval( $order->get_total() );

        if ( ! $include_tax ) {
            $total -= floatval( $order->get_total_tax() );
        }

        if ( ! $include_shipping ) {
            $total -= floatval( $order->get_shipping_total() );
            if ( ! $include_tax ) {
                $total -= floatval( $order->get_shipping_tax() );
            }
        }

        // Subtract any refunds
        $total -= floatval( $order->get_total_refunded() );

        return max( 0, $total );
    }

    /**
     * Deduct points when order is refunded
     */
    public function deduct_points_on_refund( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $points_awarded = $order->get_meta( '_wpr_points_awarded', true );
        $points_refunded = $order->get_meta( '_wpr_points_refunded', true );

        if ( $points_awarded !== 'yes' || $points_refunded === 'yes' ) {
            return;
        }

        $user_id = $order->get_user_id();
        $points  = floatval( $order->get_meta( '_wpr_points_earned', true ) );

        if ( ! $user_id || $points <= 0 ) {
            return;
        }

        $description = sprintf(
            __( 'Points deducted for refunded Order #%s', 'woo-points-rewards' ),
            $order->get_order_number()
        );

        WPR_Points_Manager::deduct_points( $user_id, $points, 'refund', $order_id, $description );

        $order->update_meta_data( '_wpr_points_refunded', 'yes' );
        $order->save();

        $order->add_order_note(
            sprintf(
                __( 'WooPoints: %s points deducted due to refund.', 'woo-points-rewards' ),
                number_format( $points )
            )
        );
    }

    /**
     * Display points earned on thank you page
     */
    public function display_points_on_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $points = $order->get_meta( '_wpr_points_earned', true );

        if ( $points ) {
            echo '<div class="wpr-thankyou-notice" style="background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%); color: #d4d4d4; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center; border: 1px solid rgba(255,255,255,0.1);">';
            echo '<p style="font-size: 18px; margin: 0; font-weight: 600;">🎉 ' . sprintf(
                esc_html__( 'You earned %s points from this order!', 'woo-points-rewards' ),
                '<strong>' . esc_html( number_format( $points ) ) . '</strong>'
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Display points earned in order emails
     */
    public function display_points_in_email( $order, $sent_to_admin, $plain_text, $email ) {
        if ( $sent_to_admin ) {
            return;
        }

        $points = $order->get_meta( '_wpr_points_earned', true );

        if ( $points ) {
            if ( $plain_text ) {
                echo "\n" . sprintf(
                    esc_html__( 'You earned %s points from this order!', 'woo-points-rewards' ),
                    number_format( $points )
                ) . "\n";
            } else {
                echo '<div style="background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%); color: #d4d4d4; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center; border: 1px solid rgba(255,255,255,0.1);">';
                echo '<p style="margin: 0; font-size: 16px;">🎉 ' . sprintf(
                    esc_html__( 'You earned %s points from this order!', 'woo-points-rewards' ),
                    '<strong>' . esc_html( number_format( $points ) ) . '</strong>'
                ) . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Calculate preview points from the current cart
     *
     * @return int
     */
    private function calculate_cart_points() {
        if ( ! WC()->cart ) {
            return 0;
        }

        $cart_total       = floatval( WC()->cart->get_total( 'edit' ) );
        $include_tax      = get_option( 'wpr_include_tax', 'no' ) === 'yes';
        $include_shipping = get_option( 'wpr_include_shipping', 'no' ) === 'yes';

        if ( ! $include_tax ) {
            $cart_total -= floatval( WC()->cart->get_total_tax() );
        }

        if ( ! $include_shipping ) {
            $cart_total -= floatval( WC()->cart->get_shipping_total() );
            if ( ! $include_tax ) {
                $cart_total -= floatval( WC()->cart->get_shipping_tax() );
            }
        }

        $rate   = floatval( get_option( 'wpr_points_rate', 1 ) );
        $points = floor( max( 0, $cart_total ) * $rate );

        return $points;
    }

    /**
     * Display points preview banner on checkout page
     */
    public function display_checkout_points_preview() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $points = $this->calculate_cart_points();
        if ( $points <= 0 ) {
            return;
        }

        echo '<div class="wpr-checkout-points-preview" style="background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%); color: #d4d4d4; padding: 16px 24px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 15px; border: 1px solid rgba(255,255,255,0.1);">';
        echo '<span style="font-size: 28px;">⭐</span>';
        echo '<span>' . sprintf(
            esc_html__( 'Complete this order and earn %s reward points!', 'woo-points-rewards' ),
            '<strong style="font-size: 18px;">' . esc_html( number_format( $points ) ) . '</strong>'
        ) . '</span>';
        echo '</div>';
    }

    /**
     * Display points preview banner on cart page
     */
    public function display_cart_points_preview() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $points = $this->calculate_cart_points();
        if ( $points <= 0 ) {
            return;
        }

        echo '<div class="wpr-cart-points-preview" style="background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%); color: #d4d4d4; padding: 14px 20px; border-radius: 10px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 14px; border: 1px solid rgba(255,255,255,0.1);">';
        echo '<span style="font-size: 24px;">⭐</span>';
        echo '<span>' . sprintf(
            esc_html__( 'You will earn %s reward points from this order!', 'woo-points-rewards' ),
            '<strong>' . esc_html( number_format( $points ) ) . '</strong>'
        ) . '</span>';
        echo '</div>';
    }

    /**
     * Display points inline above the Place Order button
     */
    public function display_checkout_points_inline() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $points = $this->calculate_cart_points();
        if ( $points <= 0 ) {
            return;
        }

        echo '<div class="wpr-checkout-inline-points" style="background: rgba(160, 160, 160, 0.08); border: 1px solid rgba(160, 160, 160, 0.2); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; text-align: center; font-size: 14px; color: #555;">';
        echo '⭐ ' . sprintf(
            esc_html__( 'You earn %s points with this purchase', 'woo-points-rewards' ),
            '<strong style="color: #333;">' . esc_html( number_format( $points ) ) . '</strong>'
        );
        echo '</div>';
    }
}
