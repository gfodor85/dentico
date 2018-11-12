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
});