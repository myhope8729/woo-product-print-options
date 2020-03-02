<?php
/**
 * Single variation display
 *
 * This is a javascript-based template for single variations (see https://codex.wordpress.org/Javascript_Reference/wp.template).
 * The values will be dynamically replaced after selecting attributes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

?>
<script type="text/template" id="tmpl-variation-template">
	<div class="woocommerce-variation-description">{{{ data.variation.variation_description }}}</div>
	<div class="woocommerce-variation-availability">{{{ data.variation.availability_html }}}</div>
	<div class="bottom">
		<div class="panel">
			<div class="panel-left">
				<div class="flex-center">
					{{{ data.variation.price_html }}}
					<div class="cleaner"></div>
				</div>
			</div>
			<div class="panel-right">
				<button type="submit" class="single_add_to_cart_button button alt">Přidat do košíku</button>
				<div class="print">
					<label><input type="checkbox" name="print" class="print_check"> <strong>Potisk předmětu - online kalkulačka</strong></label>
				</div>
			</div>
			<div class="cleaner"></div>
		</div>
		<div id="calc" class="calc">
			<div class="full services">
				{{{ data.variation.service_list }}}
			</div>
			<div class="full colors">
				<div class="select-wrapper">
					<select id="colors" name="colors">
						<option value=''>upřesněte technologii</option>
					</select>
				</div>
			</div>
			<div class="full">
				<div id="calculate-button-cover">
					<a href="javascript:void(0)" id="calculate-button" class="button"><i class="fa fa-refresh"></i>&nbsp;&nbsp;PRICE CALCULATION</a>
				</div>
			</div>
			<div class="result">
				<div class="full">
					<h2>Cena pro zvolené množství <strong><span id="r-amount">10</span></strong></h2>
						<table cellpadding="0" cellspacing="0">
							<tbody>
								<tr>
									<td>Produkt</td>
									<td>
										<em>(<span id="r-product-piece">88,20</span> Kč / 1 ks)</em>&nbsp;&nbsp;
										<span id="r-product-total">882,00</span> Kč</td>
									</tr>
									<tr>
										<td>Potisk</td>
										<td>
											<em>(<span id="r-print-piece">85,00</span> Kč / 1 ks)</em>&nbsp;&nbsp;
											<span id="r-print-total">850,00</span> Kč</td>
									</tr>
									<tr>
										<td>Tisková příprava</td>
										<td><span id="r-settings-total">800,00</span> Kč</td>
									</tr>
									<tr class="total">
										<td colspan="2" class="text-right">
											Celkem 
											<small>
												<em>(<span id="r-product-piece-total">253,20</span> Kč / 1 ks)</em>
											</small> 
											<strong><span id="r-product-total-total">2 532,00</span> Kč</strong>
											<input type="hidden" name="price_with_print" class="price_with_print" value=""/>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
						<div class="full">
							<button type="submit" class="single_add_to_cart_button button alt">
								<i class="fa fa-shopping-cart fa-fw"></i>
								&nbsp;&nbsp;Do košíku 
								<strong>
									<span id="r-amount2">10</span> ks
								</strong> s potiskem
							</button>
						</div>
						<div class="ranges">
						</div>
						<div class="cleaner"></div>
					</div>
				</div>
			</div>
		</div>
</script>
<script type="text/template" id="tmpl-unavailable-variation-template">
	<p><?php esc_html_e( 'Sorry, this product is unavailable. Please choose a different combination.', 'woocommerce' ); ?></p>
</script>
