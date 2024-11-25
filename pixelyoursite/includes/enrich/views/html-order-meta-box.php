<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
use function PixelYourSite\isWooUseHPStorage;
use function PixelYourSite\PYS;

if(!PYS()->getOption('woo_enabled_display_data_to_orders') || !isset($orderId)) return;
include_once "function-helper.php";
$order = wc_get_order($orderId);
$data = array();
$dataAnalytics = array();
if(isWooUseHPStorage()) {
    // WooCommerce >= 3.0
    if($order) {
        $data = $order->get_meta( 'pys_enrich_data', true );
    }

} else {
    // WooCommerce < 3.0
    if(get_post_meta($orderId, "pys_enrich_data", true))
    {
        $data = get_post_meta($orderId, "pys_enrich_data",true);
    }
}

if($data && is_array($data)) :
    ?>
    <style>
        table.pys_order_meta {
            width: 100%;text-align:left
        }
        table.pys_order_meta td.border span {
            border-top: 1px solid #f1f1f1;
            display: block;
        }
        table.pys_order_meta th,
        table.pys_order_meta td {
            padding:10px
        }
    </style>
    <table class="pys_order_meta">
        <tr>
            <td colspan="2" ><strong>FIRST VISIT</strong></td>
        </tr>
        <tr>
            <td colspan="2" class="border"><span></span></td>
        </tr>
        <tr >
            <th>Landing Page:</th>
            <?php
            $landingPage = !empty($data['pys_landing']) ? $data['pys_landing'] : "No Landing Page";
            if (filter_var($landingPage, FILTER_VALIDATE_URL)) {
                echo '<td><a href="' . $landingPage . '" target="_blank">' . $landingPage . '</a></td>';
            } else {
                echo '<td>' . $landingPage . '</td>';
            }
            ?>
        </tr>
        <tr>
            <th>Traffic source:</th>
            <td><?=!empty($data['pys_source']) ? $data['pys_source'] : "No Traffic source"?></td>
        </tr>
        <?php
        if(!empty($data['pys_utm'])) {
            $utms = explode("|",$data['pys_utm']);
            \PixelYourSite\Enrich\printUtm($utms);
        }

        ?>
        <tr>
            <td colspan="2" class="border"><span></span></td>
        </tr>
        <tr>
            <td colspan="2" ><strong>LAST VISIT</strong></td>
        </tr>
        <tr>
            <td colspan="2" class="border"><span></span></td>
        </tr>
        <tr >
            <th>Landing Page:</th>
            <?php
            $lastLanding = !empty($data['last_pys_landing']) ? $data['last_pys_landing'] : "No Landing Page";
            if (filter_var($lastLanding, FILTER_VALIDATE_URL)) {
                echo '<td><a href="' . $lastLanding . '" target="_blank">' . $lastLanding . '</a></td>';
            } else {
                echo '<td>' . $lastLanding . '</td>';
            }
            ?>
        </tr>
        <tr>
            <th>Traffic source:</th>
            <td><?=!empty($data['last_pys_source']) ? $data['last_pys_source'] : "No Traffic source"?></td>
        </tr>
        <?php
        if(!empty($data['last_pys_utm'])) {
            $utms = explode("|",$data['last_pys_utm']);
            \PixelYourSite\Enrich\printUtm($utms);
        }

        ?>
        <tr>
            <td colspan="2" class="border"><span></span></td>
        </tr>
        <?php
        if(!empty($data['pys_browser_time'])) :
            $userTime = explode("|",$data['pys_browser_time']);
            ?>
            <tr >
                <th>Client's browser time</th>
                <td></td>
            </tr>
            <tr >
                <th>Hour:</th>
                <td><?=$userTime[0]; ?></td>
            </tr>
            <tr >
                <th>Day:</th>
                <td><?=$userTime[1]; ?></td>
            </tr>
            <tr >
                <th>Month:</th>
                <td><?=$userTime[2]; ?></td>
            </tr>
        <?php endif; ?>


    </table>

<?php else: ?>
    <h2>No data</h2>
<?php endif; ?>