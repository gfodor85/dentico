$(document).ready(function(){

	$(window).on("scroll",function(){
  	var wn = $(window).scrollTop();
    if(wn > 120){
    	$("#navbar").addClass("navbar-scroll");
    }
    else{
    	$("#navbar").removeClass("navbar-scroll");
    }
  });

  var $toggleButton = $('.toggle-button');
    $toggleButton.on('click', function() {
      $(this).toggleClass('button-open');
    }); 

});