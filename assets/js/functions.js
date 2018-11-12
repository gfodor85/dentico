$( document ).ready(function() {

  $('.toggle-button').on('click', function() {
    $(this).toggleClass('button-open');
  });

  $(window).scroll( function(){

    $(".slider").stop().each(function(){
      var bottom_of_object = $(this).offset().top + $(this).outerHeight(),
          bottom_of_window = $(window).scrollTop() + $(window).height();

      if ( bottom_of_window > bottom_of_object ) {
        $(".slider").slideDown("slow");
      }
      if ( bottom_of_window < bottom_of_object ) {
        $(".slider").slideUp("slow");
      }
    });  

    $('.hideme').each(function(){
      var windowOneViewHeight = $(window).height() / 100;
      var top_of_object = $(this).offset().top + ( (windowOneViewHeight * 10)); // no also add the item height, only the offset top is needed
      var bottom_of_object = $(this).offset().top + ($(this).outerHeight() - (windowOneViewHeight * 10));
      var top_of_window = $(window).scrollTop();
      var bottom_of_window = $(window).scrollTop() + $(window).height();  

      if(bottom_of_object < top_of_window) {

          $(this).css({'opacity':'0','top':'-80px'});
      }
      else if (top_of_object > bottom_of_window){
          $(this).css({'opacity':'0','top':'80px'});
      }   
      else if(bottom_of_object > top_of_window && top_of_object < bottom_of_window){
          $(this).css({'opacity':'1','top':'0'});
      }
    });

    $('.prices_slide_left').each(function(){
      var windowOneViewHeight = $(window).height() / 100;
      var top_of_object = $(this).offset().top + ( (windowOneViewHeight * 10)); // no also add the item height, only the offset top is needed
      var bottom_of_object = $(this).offset().top + ($(this).outerHeight() - (windowOneViewHeight * 10));
      var top_of_window = $(window).scrollTop();
      var bottom_of_window = $(window).scrollTop() + $(window).height();  

      if(bottom_of_object < top_of_window) {
          $(this).css({'left':'-150px', 'opacity':'0'});                    
      }
      else if (top_of_object > bottom_of_window){
          $(this).css({'left':'-150px','transition':'all 1s ease','opacity':'0'});
      }   
      else if(bottom_of_object > top_of_window && top_of_object < bottom_of_window){
          $(this).css({'left':'0', 'opacity':'1'});
      }
    });

    $('.prices_slide_right').each(function(){
      var windowOneViewHeight = $(window).height() / 100;
      var top_of_object = $(this).offset().top + ( (windowOneViewHeight * 10)); // no also add the item height, only the offset top is needed
      var bottom_of_object = $(this).offset().top + ($(this).outerHeight() - (windowOneViewHeight * 10));
      var top_of_window = $(window).scrollTop();
      var bottom_of_window = $(window).scrollTop() + $(window).height();  

      if(bottom_of_object < top_of_window) {
          $(this).css({'right':'-150px', 'opacity':'0'});                    
      }
      else if (top_of_object > bottom_of_window){
          $(this).css({'right':'-150px','transition':'all 1s ease','opacity':'0'});
      }   
      else if(bottom_of_object > top_of_window && top_of_object < bottom_of_window){
          $(this).css({'right':'0', 'opacity':'1'});
      }
    });
    
  });

  
});
