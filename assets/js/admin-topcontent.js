jQuery( document ).ready( function( $ ) {
	$( '#topcont-tabs' ).tabs();

	$( '.topcont-api-key-change' ).click( function(e) {
		e.preventDefault();
		$( this ).parent().addClass( 'topcont-hide' );
		$( this ).parent().prev().removeClass( 'topcont-hide' );
	});

	$( '.topcont-api-key-cancel' ).click( function(e) {
		e.preventDefault();
		$( this ).parent().addClass( 'topcont-hide' );
		$( this ).parent().next().removeClass( 'topcont-hide' );
	});

	$( '.topcont-translation' ).click( function(e) {
		e.preventDefault();
		$( '#topcont-tabs' ).tabs( 'option', 'active', 1 );
	});

	/**
	 * Linking Rules
	 * @since 1.2
	 */

	var $linkingRules = $("#topcont-linking-rules"),
		$ruleRow = $("#topcont-linking-rules-row"),
		$noRules = $("#topcont-no-rules"),
		currentIndex = $(".topcont-table").length;

	// Add new rule
	$('#topcont-add-rule-button').on("click", function(e) {
		e.preventDefault();

		var $newRule = $ruleRow.clone();

		$newRule.removeClass("topcont-hide");
		$newRule.removeAttr("id");

		$newRule.find("select")
			.val([])
			.attr("required", true)
			.attr('name', 'topcont-rules-categories[' + currentIndex + '][]');
		$newRule.find("textarea")
			.val("")
			.attr("required", true)
			.attr('name', 'topcont-rules-phrases[' + currentIndex + '][]');
		$newRule.find("input[type='url']")
			.val("")
			.attr("required", true)
			.attr('name', 'topcont-rules-urls[' + currentIndex + '][]');

		$newRule.appendTo($linkingRules);
		$noRules.hide();
		currentIndex++;
		handleAnyOption();
	});

	// Delete rule
	$linkingRules.on("click", ".topcont-delete-button", function(e) {
		e.preventDefault();

		$(this).closest(".topcont-table").remove();

		if ($(".topcont-table").length <= 1) {
			$noRules.show();
		}
	});

	// Replace commas with line breaks
	$linkingRules.on("input", "textarea", function() {
		var text = $(this).val().replace(/,/g, '\n');
		$(this).val(text);
	});

	// "Any" option
	function handleAnyOption() {
		$('.topcont-categories-dropdown').each(function() {
			var dropdown = $(this);
			dropdown.off('change'); // Remove existing event handlers to avoid duplicates

			dropdown.on('change', function() { console.log(2);
				var anyOptionSelected = $(this).find('option[value="0"]').is(':selected');

				if (anyOptionSelected) {
					dropdown.find('option:not([value="0"])').prop('selected', false);
				} else {
					dropdown.find('option[value="0"]').prop('selected', false);
				}
			});
		});
	}

	handleAnyOption();
});
