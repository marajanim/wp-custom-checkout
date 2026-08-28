(function ($) {
	'use strict';

	function createColumn(className) {
		var column = document.createElement('div');
		column.className = className;
		return column;
	}

	function ensurePaymentHeading(payment, config, form) {
		var paymentTitle = config.paymentHeading || 'Payment Information';
		var headings = document.querySelectorAll('.wccp-payment-heading');
		headings.forEach(function (heading) {
			if (!payment.contains(heading)) {
				heading.remove();
			}
		});
		form.querySelectorAll('h1, h2, h3, h4, h5, h6, .elementor-heading-title').forEach(function (heading) {
			if (payment.contains(heading) || heading.textContent.trim() !== paymentTitle) {
				return;
			}
			var widget = heading.closest('.elementor-widget');
			if (widget && !widget.contains(payment)) {
				widget.remove();
			} else {
				heading.remove();
			}
		});
		var heading = payment.querySelector('.wccp-payment-heading');
		if (config.showPaymentHeading === false) {
			if (heading) {
				heading.remove();
			}
			return;
		}
		if (!heading) {
			heading = document.createElement('h3');
			heading.className = 'wccp-payment-heading';
			payment.insertBefore(heading, payment.firstChild);
		}
		heading.textContent = paymentTitle;
	}

	function hideEmptyArtifacts(grid) {
		grid.querySelectorAll('.wccp-checkout-main > div, .wccp-checkout-sidebar > div').forEach(function (element) {
			var hasText = element.textContent.trim() !== '';
			var hasVisibleControl = element.querySelector('table, img, button, input:not([type="hidden"]), select, textarea, iframe');
			if (!hasText && !hasVisibleControl) {
				element.hidden = true;
			}
		});
	}

	function hideEmptyCheckoutContainers(form, grid) {
		var containers = Array.prototype.slice.call(form.querySelectorAll('.elementor-element.e-con, .elementor-widget'));
		containers.reverse().forEach(function (element) {
			var hasText = element.textContent.trim() !== '';
			var hasContent = element.querySelector('table, img, button, input, select, textarea, iframe, video, .woocommerce-notices-wrapper:not(:empty)');
			if (!hasText && !hasContent) {
				element.hidden = true;
			}
		});

		var sidebar = grid.querySelector('.wccp-checkout-sidebar');
		if (!sidebar) {
			return;
		}
		var hasSidebarContent = sidebar.textContent.trim() !== '' || sidebar.querySelector('table, img, button, input, select, textarea, iframe');
		sidebar.hidden = !hasSidebarContent;
		grid.classList.toggle('wccp-no-sidebar', !hasSidebarContent);
	}

	function removeOrphanContainers(form, grid) {
		Array.prototype.slice.call(form.children).forEach(function (element) {
			if (element !== grid && element.matches('#customer_details, #order_review_heading, #order_review, #payment, .woocommerce-checkout-payment')) {
				element.remove();
			}
		});
		form.querySelectorAll(':scope > .wccp-checkout-grid').forEach(function (candidate) {
			if (candidate !== grid) {
				candidate.remove();
			}
		});
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
		form.querySelectorAll('#order_review .quantity, #order_review input.qty, #order_review .minus, #order_review .plus').forEach(function (quantityControl) {
			quantityControl.remove();
		});
		if (payment) {
			ensurePaymentHeading(payment, config, form);
		}
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
		removeOrphanContainers(form, grid);
		hideEmptyArtifacts(grid);
		hideEmptyCheckoutContainers(form, grid);

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
