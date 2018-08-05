<?php
/**
 *
 * organisations.php
 * @author Mahbub Alam <makjoybd@gmail.com>
 * @version 1.0
 * @package woo-donation-calculate
 */

if ( ! defined( 'ABSPATH' ) )
{
	die();
}

$base_url = admin_url( 'options-general.php?page=woo-donation-organisations' );

$organisations = WOO_Donation_Calculate::get_organisations();

$status = isset( $_GET['status'] ) ? intval( esc_attr( $_GET['status'] ) ) : 0;

?>

<div class="wrap woo-donation-organisation">
    <h2>Donation Organisations</h2>


	<?php WOO_Donation_Calculate::render_status_message( $status ) ?>

    <form action="<?php echo admin_url(); ?>" method="POST">
		<?php wp_nonce_field( '__woo_donation_add_organisations', '_woo_org_token' ); ?>
        <input type="text" name="org-name" class="org-name" value="" placeholder="Name of the organisation">
        <button type="submit" class="button-primary button-save">Add</button>
    </form>

	<?php if ( empty( $organisations ) ): ?>
        <div class="alert alert-warning">You don't have any organisation saved. Add one form the form above.</div>
	<?php else: ?>

        <h3>List of organisations:</h3>

        <ul class="organisation-list">
			<?php foreach ( $organisations as $key => $organisation ): ?>
                <li>
                    <span class="remove"><a
                                href="<?php echo wp_nonce_url( add_query_arg( array( "organisation_key" => $key ), $base_url ), "__woo_donation_remove_organisation", "_woo_org_token" ); ?>">&times;</a></span>
                    <span class="organisation-name"><?php echo $organisation; ?></span>
                </li>
			<?php endforeach; ?>
        </ul>

	<?php endif; ?>

    <style>
        .woo-donation-organisation form {
            margin: 20px 0;
            width: 80%;
            padding: 20px;
            border: 1px dashed #ccc;
            border-radius: 3px;
        }

        .woo-donation-organisation form .org-name {
            width: 300px;
            font-size: 16px;
            padding: 10px 5px;
            vertical-align: middle;
        }

        .woo-donation-organisation .button-save {
            padding: 6px 20px;
            display: inline-block;
            height: auto;
            vertical-align: middle;
        }

        .woo-donation-organisation .alert{
            background: #fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            padding: 10px 12px;
            margin: 5px 0 15px;
            border-left: 4px solid #fff;
        }

        .woo-donation-organisation .alert-message{
            border-left-color: #46b450;
        }

        .woo-donation-organisation .alert-error{
            border-left-color: #ff000c;
        }

        .woo-donation-organisation .alert-warning{
            border-left-color: #ffd735;
        }

        .organisation-list li .remove{
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }

        .organisation-list li .remove a{
            display: inline-block;
            text-decoration: none;
            font-size: 16px;
            background: #ff3434;
            color: #fff;
            border-radius: 16px;
            width: 16px;
            height: 16px;
            text-align: center;
            line-height: 14px;
        }

    </style>

</div>