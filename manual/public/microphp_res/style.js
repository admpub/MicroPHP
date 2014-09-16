   
/**
 * MiniCMS 
 *
 * @package		MiniCMS 
 * @author		狂奔的蜗牛
 * @email		672308444@163.com
 * @copyright	        Copyright (c) 2013 - 2014, 狂奔的蜗牛, Inc.
 * @link		https://git.oschina.net/snail/microphp/
 * @since		Version 1.0
 * @createdtime         2014-4-3 16:24:27
 */

$(function() {
    if(parseInt($('html,body').width())<=970){
        $('.manual_left').css({'max-height':'none','overflow-y':'hidden'});
    }
    $('.cms-page .page_btn_go').addClass('btn').addClass('btn-xs').addClass('btn-default').css({'text-decoration': 'none', 'margin-top': '-3px'});
    $('.cms-page .page_input_num input').css({'padding': '0px 3px', 'font-size': 'inherit', border: '1px #cccccc solid'});

    var offset = 300;
    var duration = 500;
    $(window).scroll(function() {
        if ($(this).scrollTop() > offset) {
            $('#gotoTop').fadeIn(duration);
        } else {
            $('#gotoTop').fadeOut(duration);
        }
    });
    $('#gotoTop').click(function(event) {
        event.preventDefault();
        $('html, body').animate({scrollTop: 0}, duration);
        return false;
    });
    $('.manual_left a').click(function() {
         if(!$('.navbar-toggle-btn').is(':visible')){
             $('html, body').animate({scrollTop: 0}, duration);
             $('.manual_nav_fix').hide();
         }else{
             $('.manual_nav_fix').show();
             $.scrollTo($('.manual_nav_fix'), duration);
         }
        $('.manual_right>div').remove();
        $('.manual_right>iframe').contents().find('body').html('正在加载...');
        $('.manual_right>iframe').attr('src',this.href+'&from=tab').show();
        $(this).parent().parent().find('li').removeClass('active');
        $(this).parent().addClass('active');
        return false;
    });
    $('.page_bar_content a').click(function(){return false;});
});
 