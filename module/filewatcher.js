Zepto(function($){
    watcher_init();

});
function clearDog() {
    filewatcherdog = "";
    $('.watcherResult').html(filewatcherdog);
}

function stopDog() {
    dog = false;
}

function startDog() {
    dog = true;
}

function watcher_init(){
    var timer = setTimeout(function f() {
        if (dog === true) {
            watcher_refresh();
        }
        timer = setTimeout(f, ouputTime);
    }, ouputTime);
}

function setTimer() {
    var timer = document.getElementById('timer').value;
    timer = parseInt(timer);
    if (timer > 0) {
        ouputTime = timer;
    }

}

function watcher_refresh(){
    send_post({watcherDog:''}, function(res){
        filewatcherdog = filewatcherdog + res;
        $('.watcherResult').html(filewatcherdog);
        var div = document.getElementById('msg');
        div.scrollTop = div.scrollHeight;
    });
}