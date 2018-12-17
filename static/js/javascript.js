setInterval(function(){phpJavascriptClock();},1000);
function phpJavascriptClock(){
    var today=new Date();
    var d=today.getDay();
    var h=today.getHours();
    var m=today.getMinutes();
    var s=today.getSeconds();

    if (h<10){h="0"+h}
    if (s<10){s="0"+s}
    if (m<10){m="0"+m}

    var weekday = new Array(7);
    weekday[0]=  "Sunday";
    weekday[1] = "Monday";
    weekday[2] = "Tuesday";
    weekday[3] = "Wednesday";
    weekday[4] = "Thursday";
    weekday[5] = "Friday";
    weekday[6] = "Saturday";

    var dag = weekday[d];
    document.getElementById('timer').innerHTML = dag+" "+h+":"+m+":"+s
}

