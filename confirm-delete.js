$(document).ready(function() {

	var inputCells = jQuery("input[class*='cell']")

	inputCells.each(function(){

	var $width	= $(this).css('width');
	var $height	= $(this).css('height');
	var $b_color	= $(this).css('border-left-color');

	/*
	$(this).hover(
		function(){ // in 
			console.log("\tin");

			$(this).css('width','200px');
			$(this).css('height','70px');
			$(this).css('border','1px solid black');

		}, function(){ //out 

			console.log("out");
			$(this).css('width',$width);
			$(this).css('height',$height);
			$(this).css('border',"1px solid "+$b_color);
		});
		*/
	});

	var pageForms = jQuery('form');


	pageForms.find('input[type="submit"]').live('click', function(event) {
	//pageForms.find("input[name*='do_delete']").live('click', function(event) {
		var deleteButton = this;
		var parentForm = jQuery(jQuery(this).parents('form')[0]);

		var $delterm = deleteButton.name == "do_delete_term";
		var $dellang = deleteButton.name == "do_delete_lang";

		if ( $dellang || $delterm ) {
			parentForm.data('delete-button',deleteButton);
		}
	});

	/*
	*/

	pageForms.live('submit', function(event) {

		// The form that was submitted
		var theForm = jQuery(this);

		/*
		theForm.append($("#disable_confirm").clone().css('display','none')); // carry over checked/unchecked 
		theForm.append($("#disable_expand").clone().css('display','none'));
		*/


		var deleteButton = theForm.data('delete-button');
	
		if (deleteButton == undefined){
			theForm.submit();
			return true;
		}

		var $delterm = deleteButton.name == "do_delete_term";
		var $dellang = deleteButton.name == "do_delete_lang";

		if ($dellang || $delterm) {

			if ($dellang){
				var $target = "input[name='newname']"
				var $targett= "language";
			}

			if ($delterm){
				var $target = "input[name='term']"
				var $targett= "term";
			}

				var $input = $(theForm).children($target);
				//var $count = $($input).size();
				//var $lang3 = $($lang).attr('value');
				var $lang  = $($input[0]);
				var $name = $lang.attr('value');
				var $msg = "Remove the "+$targett+":<br> "+$name+"?";

			//$("p#TB-lang").html($("input#emailTB").val());
			
			$("#TBmsg").html( $msg );

			tb_show('Please confirm delete action',
				'TB_inline?height=115&amp;width=250&amp;inlineId=TBcontent');

			$("#TBcancel").focus();
			$('input#TBsubmit').click(function(){
				$("#TBok").attr('name','1');
				theForm.submit();
			});

			if ( $("#TBok").attr('name') == 0 ){
				// prevent submission if TBok not set
				event.preventDefault();
			} else {
				// submit form
				$("#TBok").attr('name','0');
				tb_remove();
				theForm.append('<input type="hidden" name="'+deleteButton.name+'" value="1">');
			}
		}
	});

	$('input#TBcancel').click(function(){
		tb_remove();
	});
});

