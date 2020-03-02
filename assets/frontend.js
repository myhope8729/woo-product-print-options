jQuery(document).ready(function($){
	var color_data;
	$(document).on('click', ".print_check", function(){
		if ($(this).is(":checked")){
			$("#calc").show();
		}		
	});
	$(document).on("change", "#services", function(){
		var ajax_url = woocommerce_params.ajax_url;
		$("#colors").empty();
		$("#colors").append("<option value=''>upřesněte technologii</option>");
		$(".result").hide();
		if ($(this).val() != ""){
			$.ajax({
				url: ajax_url,
				method: 'POST',
				dataType: 'JSON',
				data: {"action":"get_colors", "service_id":$(this).val()},
				success:function(data){
					if (Object.keys(data).length > 0){
						color_data = data;
						$.each(data, function(ind, color){
							$("#colors").append("<option value='"+ color.name + "'>" + color.name + "</option>");
						});
					}
				}
			});	
		}
	});

	$(document).on("change", "#colors", function(){
		$(".result").hide();
		var color = $(this).val();
		var min_qty = 0;
		if (color_data[color].range.length > 0){
			min_qty = 999999;
		}
		$.each(color_data[color].range, function(ind, range){
			if (min_qty > parseInt(range.from)){
				min_qty = parseInt(range.from);
			}
		});
		$(".min_qty").html(min_qty);
	});
	$(document).on("click", "#calculate-button", function(){
		var color = $("#colors").val();
		var selected_range = 0;
		var min_qty = 0;
		var curr_qty = parseInt($(".qty").val());
		$(".ranges").empty();
		if (color_data[color].range.length > 0){
			min_qty = 999999;
		}
		if (color != ""){
			var prod_price_obj = $(".flex-center .woocommerce-Price-amount").clone();
			prod_price_obj.find("span").remove();
			var prod_price = parseFloat(prod_price_obj.html());
			var press_fee = parseFloat(color_data[color].preparation);

			$.each(color_data[color].range, function(ind, range){
				if (min_qty > parseInt(range.from)){
					min_qty = parseInt(range.from);
				}
				if (curr_qty >= parseInt(range.from) && curr_qty <= parseInt(range.to)){
					selected_range = ind;
				}
				var product_print_price = ((parseFloat(range.price) + prod_price ) * parseInt(range.from) + press_fee) / parseInt(range.from);
				$(".ranges").append("<div class='full'>\
					<h3>cena s potiskem od&nbsp;\
					<span class='range_amount'>" + range.from + "&nbsp;ks&nbsp;\
					<div class='pull-right'>" + product_print_price.toFixed(2) + "Kč / 1 ks</div");
			});
			if (curr_qty < min_qty){
				alert("Minimum quantity for this range is " + min_qty + ".");
				return false;
			}
			$("#r-amount").html(curr_qty);
			
			var prod_total = parseFloat(prod_price * curr_qty).toFixed(2);
			var print_total = parseFloat(color_data[color].range[selected_range].price) * curr_qty;
			var total_price = parseFloat(prod_total) + parseFloat(print_total) + parseFloat(color_data[color].preparation);
			var piece_total = total_price / curr_qty;
			$("#r-product-piece").html(prod_price);
			$("#r-product-total").html(prod_total);
			$("#r-print-piece").html(color_data[color].range[selected_range].price);
			$("#r-print-total").html(parseFloat(color_data[color].range[selected_range].price * curr_qty).toFixed(2));
			$("#r-settings-total").html(color_data[color].preparation);
			$("#r-product-piece-total").html(piece_total.toFixed(2));
			$(".price_with_print").val(piece_total.toFixed(2));
			$("#r-product-total-total").html(total_price);
			$("#r-amount2").html(curr_qty);
			$(".result").show();
		}
	});
});