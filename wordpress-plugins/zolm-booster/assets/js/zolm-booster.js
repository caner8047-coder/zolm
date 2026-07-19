jQuery(function($){
    function escapeHtml(value){return $("<div>").text(String(value??"")).html();}
    function safeUrl(value){
        try{var url=new URL(String(value||""),window.location.origin);return ["http:","https:"].includes(url.protocol)?url.href:"";}catch(e){return "";}
    }
    function parseMedia(value){
        if(Array.isArray(value))return value;
        if(!value)return [];
        try{var parsed=JSON.parse(value);return Array.isArray(parsed)?parsed:[];}catch(e){return [];}
    }
    $(document).on("click",".zolm-filter-btn",function(){
        var rating=$(this).data("rating");
        $(this).siblings(".zolm-filter-btn").removeClass("active");
        $(this).addClass("active");
        var items=$(".zolm-review-item");
        items.each(function(){
            var r=$(this).data("rating");
            var hasPhoto=$(this).data("has-photo");
            var show=false;
            if(rating===0)show=true;
            else if(rating==="photo")show=hasPhoto==="1";
            else if(rating>0)show=r===rating;
            $(this).toggle(show);
        });
        if($(".zolm-review-item:visible").length===0){
            if($(".zolm-no-results").length===0)$(".zolm-reviews-list").append('<p class="zolm-no-results">Bu filtre için yorum bulunamadı.</p>');
        }else{
            $(".zolm-no-results").remove();
        }
    });
    $(document).on("change",".zolm-sort-select",function(){
        var sortVal=$(this).val();
        var items=$(".zolm-review-item").get();
        items.sort(function(a,b){
            switch(sortVal){
                case "newest":return new Date($(b).data("date"))-new Date($(a).data("date"));
                case "oldest":return new Date($(a).data("date"))-new Date($(b).data("date"));
                case "highest":return $(b).data("rating")-$(a).data("rating");
                case "lowest":return $(a).data("rating")-$(b).data("rating");
                case "helpful":return $(b).data("helpful")-$(a).data("helpful");
                default:return 0;
            }
        });
        $(".zolm-reviews-list").empty().append(items);
    });
    $(document).on("click",".zolm-review-photo",function(){
        var src=safeUrl($(this).data("src"));
        if(!src)return;
        $("<div>").addClass("zolm-lightbox").append($("<img>").attr("src",src)).appendTo("body").fadeIn(200);
    });
    $(document).on("click",".zolm-lightbox",function(){
        $(this).fadeOut(200,function(){$(this).remove();});
    });
    $(document).on("click",".zolm-load-more",function(){
        var btn=$(this);var productId=btn.data("product-id");var offset=btn.data("offset");
        btn.text("Yükleniyor...").prop("disabled",true);
        $.ajax({url:zolmBooster.restUrl+"/products/"+productId+"/reviews",method:"GET",data:{offset:offset},success:function(resp){
            if(resp.ok&&resp.reviews&&resp.reviews.length){
                resp.reviews.forEach(function(r){
                    var media=parseMedia(r.review_media);
                    var photosHtml="";
                    if(media.length){photosHtml='<div class="zolm-review-photos">';media.forEach(function(m){var u=safeUrl(m.url||m);if(u)photosHtml+='<div class="zolm-review-photo" data-src="'+escapeHtml(u)+'"><img src="'+escapeHtml(u)+'" loading="lazy" alt=""/></div>';});photosHtml+="</div>";}
                    var starsHtml="";for(var i=1;i<=5;i++){if(r.rating>=i)starsHtml+='<span class="zolm-star full">★</span>';else if(r.rating>=i-0.5)starsHtml+='<span class="zolm-star half">★</span>';else starsHtml+='<span class="zolm-star empty">★</span>';}
                    var reviewer=escapeHtml(r.reviewer_name||"Anonim");var reviewedAt=escapeHtml(r.reviewed_at||"");var helpful=Math.max(0,parseInt(r.helpful_count||0,10)||0);
                    var html='<div class="zolm-review-item" data-rating="'+Math.max(1,Math.min(5,parseInt(r.rating||0,10)||1))+'" data-has-photo="'+(media.length?"1":"0")+'" data-date="'+reviewedAt+'" data-helpful="'+helpful+'"><div class="zolm-review-header"><div class="zolm-reviewer-avatar">'+reviewer.charAt(0)+'</div><div class="zolm-reviewer-info"><span class="zolm-reviewer-name">'+reviewer+'</span><div class="zolm-review-meta"><span class="zolm-review-stars"><div class="zolm-stars">'+starsHtml+'</div></span>'+(reviewedAt?'<span class="zolm-review-date">'+reviewedAt+'</span>':"")+'</div></div></div><p class="zolm-review-text">'+escapeHtml(r.comment||"")+'</p>'+photosHtml+(helpful>0?'<span class="zolm-helpful">'+helpful+' kişi faydalı buldu</span>':"")+"</div>";
                    $(".zolm-reviews-list").append(html);
                });
                btn.data("offset",offset+resp.reviews.length).text("Daha fazla yorum göster").prop("disabled",false);
                if(resp.reviews.length<20)btn.hide();
            }else{btn.hide();}
        },error:function(){btn.text("Hata oluştu").prop("disabled",false);}});
    });
});
var zolmBooster=window.zolmBooster||{};window.zolmBooster=zolmBooster;
