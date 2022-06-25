if(!window.awz_yd_modal){
    window.awz_yd_modal = {
        last_items: [],
        lastSign: '',
        objectManager: null,
        initedHandlers: false,
        map: false,
        loader_template: function(){
            var loader_mess = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_LOADER') : 'Loading...';
            return '<div class="awz-yd-preload"><div class="awz-yd-load">'+loader_mess+'</div></div>';
        },
        template: function(title){

            var msg1 = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_PAY_CASH') : 'cash';
            var msg2 = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_PAY_CARD') : 'card';
            var msg3 = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_PAY_PREPAY') : 'prepay';
            var msg4 = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_TYPE_PVZ') : 'pick-up point';
            var msg5 = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_TYPE_TERMINAL') : 'terminal';
            
            var close_msg = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_CLOSE') : 'close';
            var ht = '<div class="awz-yd-modal-content-bg"></div>' +
                '<a class="awz-yd-close" href="#"><div>\n' +
                '        <div class="awz-yd-close-leftright"></div>\n' +
                '        <div class="awz-yd-close-rightleft"></div>\n' +
                '        <span class="awz-yd-close-close-btn">'+close_msg+'</span>\n' +
                '    </div></a>' +
                '<div class="awz-yd-modal-content"><div class="awz-yd-modal-content-wrap">'+
                '<div class="awz-yd-modal-header">'+
                ''+title+
                '</div>'+
                '<div class="awz-yd-modal-body"><div class="awz-yd-contentWrap"></div>' +
                '<div class="awz-yd-modal-filter-payment-wrap">' +
                '<a href="#" class="awz-yd-modal-filter-payment" data-payment="cash_on_receipt">'+msg1+'</a>' +
                '<a href="#" class="awz-yd-modal-filter-payment" data-payment="card_on_receipt">'+msg2+'</a>' +
                '<a href="#" class="awz-yd-modal-filter-payment" data-payment="already_paid">'+msg3+'</a>' +
                '<a href="#" class="awz-yd-modal-filter-type" data-type="pickup_point">'+msg4+'</a>' +
                '<a href="#" class="awz-yd-modal-filter-type" data-type="terminal">'+msg5+'</a>' +
                '</div>' +
                '</div>'+
                '</div></div>';

            return ht;
        },
        hideLoader: function(){
            $('.awz-yd-preload').remove();
        },
        setError: function (mess){
            $('.awz-yd-contentWrap').html('<div class="awz-yd-modal-error">'+mess+'</div>');
            this.hideLoader();
        },
        hide: function(){
            $('.awz-yd-modal-content').remove();
            $('.awz-yd-modal-content-bg').remove();
            $('.awz-yd-close').remove();
        },
        show: function(title, params){
            this.lastSign = params;
            $('body').append(this.template(title));
            var h = $(window).height();
            var w = $(window).width();
            if(w > 860) {
                w = Math.ceil(w*0.8);
                h = Math.ceil(h*0.8);
                $('.awz-yd-modal-content-wrap').css({
                    'margin-top':Math.ceil(($(window).height()-h)/2)+'px',
                    'width':w+'px',
                    'height': h+'px'
                });
            }else{
                $('.awz-yd-close').addClass('awz-yd-close-mobile');
                w = Math.ceil(w);
                h = Math.ceil(h);
                $('.awz-yd-modal-content-wrap').css({'width':w+'px', 'height': h+'px'});
            }
            $('.awz-yd-modal-body .awz-yd-contentWrap').append('<div class="awz-yd-map" id="awz-yd-map"></div>');
            var hmap = $('.awz-yd-modal-content-wrap').height() - $('.awz-yd-modal-header').height() - 30;
            $('.awz-yd-modal-body').css({'height':hmap+'px'});

            this.getPickpointsList(params);
        },
        loadBaloonAjax: function(e, params, el, pvz, callback){

            var serv_error = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_ERR') : 'server error';
            var loader_mess = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_LOADER') : 'Loading...';

            if(e){
                var objectId = e.get('objectId'),
                    obj = window.awz_yd_modal.objectManager.objects.getById(objectId);
                obj.properties.balloonContent = loader_mess;
                window.awz_yd_modal.objectManager.objects.balloon.open(objectId);
                var id = obj.properties.id;
            }else{
                var id = pvz;
                el.html(loader_mess);
            }

            $.ajax({
                url: '/bitrix/services/main/ajax.php?action=awz:ydelivery.api.pickpoints.baloon',
                method: 'POST',
                data: {
                    signedParameters: params,
                    id: id
                },
                success: function(resp){
                    var data = resp.data;
                    console.log(data);
                    if(resp.status === 'error'){
                        var msg = '';
                        var k;
                        for(k in resp.errors){
                            var err = resp.errors[k];
                            msg += err.message+'<br><br>';
                        }
                        if(e) {
                            obj.properties.balloonContent = msg;
                            window.awz_yd_modal.objectManager.objects.balloon.setData(obj);
                        }else{
                            el.html(msg);
                        }
                    }else if(resp.status === 'success'){
                        if(e) {
                            obj.properties.balloonContent = data;
                            window.awz_yd_modal.objectManager.objects.balloon.setData(obj);
                            //debugger;
                        }else{
                            el.html(data);
                        }
                    }
                    if(typeof callback === 'function'){
                        callback.call();
                    }
                },
                error: function(){
                    if(e) {
                        obj.properties.balloonContent = serv_error;
                        window.awz_yd_modal.objectManager.objects.balloon.setData(obj);
                    }else{
                        el.html(serv_error);
                    }
                    if(typeof callback === 'function'){
                        callback.call();
                    }
                }
            });
        },
        initMap: function(){
            this.map = new ymaps.Map("awz-yd-map",{
                center: [55.7522, 37.6156],
                zoom: 14,
                controls: ['zoomControl', 'searchControl']
            },{
                balloonMaxWidth: 280
            });

        },
        checkFilter: function(){
            var payments = [];
            $('.awz-yd-modal-filter-payment').each(function(){
                if($(this).hasClass('active')) payments.push($(this).attr('data-payment'));
            });
            //if(!payments.length){
            //    $('.awz-yd-modal-filter-payment').addClass('active');
            //    return this.checkFilter();
            //}

            var type = [];
            $('.awz-yd-modal-filter-type').each(function(){
                if($(this).hasClass('active')) type.push($(this).attr('data-type'));
            });
            //if(!type.length){
            //    $('.awz-yd-modal-filter-type').addClass('active');
            //    return this.checkFilter();
            //}

            var objectsArray = window.awz_yd_modal.getPoints(payments, type);
            window.awz_yd_modal.objectManager.add(objectsArray);
        },
        initHandlers: function(){
            if(this.initedHandlers) return;
            this.initedHandlers = true;
            $(document).on('click','.awz-yd-modal-filter-payment',function(e){
                if(!!e) e.preventDefault();
                window.awz_yd_modal.objectManager.removeAll();
                if($(this).hasClass('active')){
                    $(this).removeClass('active');
                }else{
                    $(this).addClass('active');
                }
                window.awz_yd_modal.checkFilter();
            });
            $(document).on('click','.awz-yd-modal-filter-type',function(e){
                if(!!e) e.preventDefault();
                window.awz_yd_modal.objectManager.removeAll();
                if($(this).hasClass('active')){
                    $(this).removeClass('active');
                }else{
                    $(this).addClass('active');
                }
                window.awz_yd_modal.checkFilter();
            });
            $(document).on('click','.awz-yd-modal-content-bg',function(e){
                if(!!e) e.preventDefault();
                window.awz_yd_modal.hide();
            });
            $(document).on('click','.awz-yd-close',function(e){
                if(!!e) e.preventDefault();
                window.awz_yd_modal.hide();
            });
            $(document).on('click','.awz-yd-select-pvzadmin',function(e){
                if(!!e) e.preventDefault();
                $('#AWZ_YD_POINT_ID').val($(this).attr('data-id'));
            });
            $(document).on('click','.awz-yd-select-pvz',function(e){
                if(!!e) e.preventDefault();
                var form = $('#AWZ_YD_POINT_LINK').parents('form');
                if(!$('#AWZ_YD_POINT_ID').length){
                    console.log(form);
                    form.prepend('<input type="hidden" name="AWZ_YD_POINT_ID" id="AWZ_YD_POINT_ID" value="">');
                }
                $('#AWZ_YD_POINT_ID').val($(this).attr('data-id'));
                if($('#AWZ_YD_POINT_INFO').length){
                    try{
                        window.BX.Sale.OrderAjaxComponent.sendRequest();
                    }catch (e) {
                        window.awz_yd_modal.loadBaloonAjax(
                            false, window.awz_yd_modal.lastSign,
                            $('#AWZ_YD_POINT_INFO'), $('#AWZ_YD_POINT_ID').val(),
                            function(){
                                $('#AWZ_YD_POINT_INFO .awz-yd-select-pvz').remove();
                            }
                        );
                    }
                }
                window.awz_yd_modal.hide();
            });
            //AWZ_YD_POINT_ID
        },
        setPickpointToOrder: function(){
            //awz-ydelivery-send-id
            var params = $('#awz-ydelivery-send-id-sign').val();
            $.ajax({
                url: '/bitrix/services/main/ajax.php?action=awz:ydelivery.api.pickpoints.setorder',
                method: 'POST',
                data: {
                    signedParameters: params,
                    point: $('#AWZ_YD_POINT_ID').val()
                },
                success: function(resp){
                    if(resp.status == 'success'){
                        $('#awz-ydelivery-send-id-form').append('<p class="result-ajax" style="color:green;">'+resp.data+'</p>');
                    }else{
                        $('#awz-ydelivery-send-id-form').append('<p class="result-ajax" style="color:red;">'+resp.errors+'</p>');
                    }
                },
                error: function(){

                }
            });

        },
        getPoints: function(payment, type){
            var objectsArray = [];
            var k;
            for(k in window.awz_yd_modal.last_items){
                var item = window.awz_yd_modal.last_items[k];
                if(payment && payment.length){
                    var k2;
                    var checkPayment = false;
                    for(k2 in payment){
                        if(item.payment_methods.indexOf(payment[k2])>-1) checkPayment = true;
                    }
                    if(!checkPayment) continue;
                }
                if(type && type.length){
                    var k2;
                    var checkType = false;
                    for(k2 in type){
                        if(item.type.indexOf(type[k2])>-1) checkType = true;
                    }
                    if(!checkType) continue;
                }

                objectsArray.push({
                    "type": "Feature",
                    "id": item.id,
                    "geometry": {
                        "type": "Point",
                        "coordinates": [item.position.latitude,item.position.longitude]
                    },
                    "options":{
                        iconLayout: 'default#image',
                        iconImageHref: "/bitrix/images/awz.ydelivery/yandexPoint.svg",
                        iconImageSize: [32, 42],
                        iconImageOffset: [-16, -42],
                        preset: 'islands#blackClusterIcons',
                        openEmptyBalloon: true
                    },
                    "properties":{
                        balloonContent: '',
                        id: item.id
                    }
                });
            }
            return objectsArray;
        },
        getPickpointsList: function(params){
            //console.log(params);

            $('.awz-yd-modal-body').append(window.awz_yd_modal.loader_template());
            //debugger;

            var serv_error = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_SERV_ERR') : 'server error';
            var choise_msg = window.BX ? window.BX.message('AWZ_YDELIVERY_JS_CHOISE') : 'choise';

            $.ajax({
                url: '/bitrix/services/main/ajax.php?action=awz:ydelivery.api.pickpoints.list',
                method: 'POST',
                data: {
                    signedParameters: params
                },
                success: function(resp){
                    var data = resp.data;
                    window.awz_yd_modal.hideLoader();
                    if(data && data.hasOwnProperty('items')){

                        ymaps.ready(function(){
                            window.awz_yd_modal.initMap();

                            var customBalloonContentLayout = ymaps.templateLayoutFactory.createClass('<div class="yd-popup-balloon-content"></div>');
                            window.awz_yd_modal.objectManager = new ymaps.ObjectManager({
                                clusterize: true,
                                clusterBalloonContentLayout: customBalloonContentLayout,
                                geoObjectOpenBalloonOnClick: false
                            });
                            window.awz_yd_modal.objectManager.clusters.options.set('preset', 'islands#blackClusterIcons');
                            window.awz_yd_modal.objectManager.clusters.events.add(['balloonopen'], function(e){
                                console.log(e);
                            });
                            window.awz_yd_modal.objectManager.objects.events.add(['click'], function(e){
                                window.awz_yd_modal.loadBaloonAjax(e, params);
                            });

                            window.awz_yd_modal.last_items = data.items;

                            var objectsArray = window.awz_yd_modal.getPoints();

                            window.awz_yd_modal.objectManager.add(objectsArray);

                            window.awz_yd_modal.map.geoObjects.add(window.awz_yd_modal.objectManager);

                            window.awz_yd_modal.map.setBounds(window.awz_yd_modal.map.geoObjects.getBounds());
                            window.awz_yd_modal.map.setZoom(window.awz_yd_modal.map.getZoom()-1);
                        });
                    }else if(resp.status === 'error'){
                        var msg = '';
                        var k;
                        for(k in resp.errors){
                            var err = resp.errors[k];
                            msg += err.message+'<br><br>';
                        }
                        window.awz_yd_modal.setError(msg);
                    }
                },
                error: function(){
                    window.awz_yd_modal.setError(serv_error);
                }
            });

        }
    };
}

$(document).ready(function(){
    console.log('register module awz.ydelivery');
    window.awz_yd_modal.initHandlers();
});