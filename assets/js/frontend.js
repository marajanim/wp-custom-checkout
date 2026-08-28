(function ($) {
	'use strict';

	function createColumn(className) {
		var column = document.createElement('div');
		column.className = className;
		return column;
	}

	function arrangeCheckout() {
		var config = window.wccpCheckout || {};
		var form = document.querySelector('form.woocommerce-checkout');
		if (!form) {
			return;
		}
		var grid = form.querySelector(':scope > .wccp-checkout-grid');
		if (!grid) {
			grid = document.createElement('div');
			grid.className = 'wccp-checkout-grid';
			grid.appendChild(createColumn('wccp-checkout-main'));
			grid.appendChild(createColumn('wccp-checkout-sidebar'));
			form.appendChild(grid);
		}

		var main = grid.querySelector('.wccp-checkout-main');
		var sidebar = grid.querySelector('.wccp-checkout-sidebar');
		var customer = form.querySelector('#customer_details');
		var heading = form.querySelector('#order_review_heading');
		var review = form.querySelector('#order_review');
		var payment = form.querySelector('.woocommerce-checkout-payment');
		if (customer && customer.parentNode !== main) {
			main.appendChild(customer);
		}
		if (config.movePayment && payment && payment.parentNode !== main) {
			main.appendChild(payment);
		}
		if (heading && heading.parentNode !== sidebar) {
			sidebar.appendChild(heading);
		}
		if (review && review.parentNode !== sidebar) {
			sidebar.appendChild(review);
		}

		var billingHeading = form.querySelector('.woocommerce-billing-fields > h3');
		if (billingHeading) {
			billingHeading.hidden = config.showBillingHeading === false;
		}
		var shippingFields = form.querySelector('.woocommerce-shipping-fields');
		if (shippingFields) {
			shippingFields.hidden = config.showShipping === false;
		}
		document.body.classList.toggle('wccp-sticky-summary', config.stickySummary === true);
	}

	$(arrangeCheckout);
	$(document.body).on('change', 'input[name="billing_delivery_area"]', function () {
		$(document.body).trigger('update_checkout');
	});
	$(document.body).on('updated_checkout payment_method_selected', arrangeCheckout);
}(jQuery));
