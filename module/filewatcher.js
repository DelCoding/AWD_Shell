Zepto(function($){
    watcher_init();

});


function watcher_init(){
    var timer = setInterval(function() {
        watcher_refresh();
    }, 10000);
}

function watcher_refresh(){
    send_post({watcherDog:''}, function(res){
        filewatcherdog = filewatcherdog + res;
        $('.watcherResult').html(filewatcherdog);
        var div = document.getElementById('msg');
        div.scrollTop = div.scrollHeight;
    });
}