@extends('layouts.master')

@section('body')
    <section class="content";>
        <div class="row">

            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border" style="background-color: ghostwhite ">
                        <h3 class="box-title">Impostazioni lista</h3>
                    </div>
                    <div class="box-body">

                        <form method="POST" id="preferenceWidget">
                            {!! Form::hidden('feed', null, ['id'=>"feed"]) !!}
                            {!! Form::hidden('sitemap', null, ['id'=>"sitemap"]) !!}

                            <div class="form-group">
                                <label for="structure_id">Struttura dati</label>
                                {!! Form::select('structure_id', $cList->structures , $cList->get('structure_id') , ['class' => "form-control", "id"=>'structure_id']) !!}
                            </div>
                            <div class="form-group">
                                <label for="model_id">Modello di struttura</label>
                                {!! Form::select('model_id', $cList->models , $cList->get('model_id') , ['class' => "form-control", "id"=>'model_id']) !!}
                            </div>
                            <div class="form-group">
                                <label for="inpage">Visualizza in</label>
                                {!! Form::select('inpage', $cList->pages , $cList->get('inpage') , ['class' => "form-control"]) !!}
                            </div>
                            <div class="form-group">
                                {{ Form::label('tags', 'Tags:') }}
                                {{ Form::select('tags[]', $cList->tags, null, ['class' => 'form-control select2-multi tagsel', 'multiple' => 'multiple', 'style'=>'width:100%']) }}
                            </div>
                            @foreach($cList->vocabularies as $vocabulary)
                                <div class="form-group form-toggle">
                                    {{ Form::label('categories'.$vocabulary->id.'[]', $vocabulary->name.":") }}
                                    {{ Form::select('categories'.$vocabulary->id.'[]', $vocabulary->categories()->pluck('name','id'), null, ['class' => "form-control select2-multi multicat", 'multiple' => 'multiple', 'style'=>'width:100%;', 'id'=>'categories'.$vocabulary->id]) }}
                                </div>
                            @endforeach
                            <div class="form-group">
                                <label for="type_order">Ordina per</label>
                                {!! Form::select('ord', $cList->selectOrder['ord'] , $cList->get('ord') , ['class' => "form-control"]) !!}
                            </div>
                            <div class="form-group">
                                {!! Form::select('dir', $cList->selectOrder['dir'] , $cList->get('dir') , ['class' => "form-control"]) !!}
                            </div>
                            <div class="form-group">
                                <label for="type_order">Scrolling</label>
                                {!! Form::select('scrolling', [''=>'','nextf'=>'Prossimo contenuto','prevf'=>'Precedente contenuto',] , $cList->get('scrolling') , ['class' => "form-control"]) !!}
                            </div>
                        </form>

                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border" style="background-color: ghostwhite ">
                        <h3 class="box-title">Feed Rss</h3>
                    </div>
                    <div class="box-body">

                        <form method="POST" id="feedrss">
                            <div class="form-group">
                                <label for="setFeed">Abilitato</label>
                                {!! Form::select('setFeed', ["No","Si"] ,$cList->get('setFeed') , ['class' => "form-control input-sm", 'id'=>"setFeed"]) !!}
                            </div>
                            <div class="feed">
                                <div class="form-group">
                                    <label for="feed_name">Nome del Feed</label>
                                    {!! Form::text('feed_name', $cList->get('feed.feed_name', "Feed Aggregatore contenuti"), ['class' => "form-control input-sm", 'id'=>"feed_name"]) !!}
                                </div>
                                <div class="form-group">
                                    <label for="feed_size">Numero massimo elementi</label>
                                    {!! Form::select('feed_size', [5=>5,10=>10,15=>15,20=>20,25=>25,30=>30] ,$cList->get('feed.feed_size') , ['class' => "form-control input-sm", 'id'=>"feed_size"]) !!}
                                </div>
                                <div class="form-group">
                                    <label for="feed_format">Tipo feed</label>
                                    {!! Form::select('feed_format', ['atom'=>"Atom 1.0",'rss2'=>"Rss 2.0"] ,$cList->get('feed.feed_format') , ['class' => "form-control input-sm", 'id'=>"feed_format"]) !!}
                                </div>
                            </div>
                        </form>

                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border" style="background-color: ghostwhite ">
                        <h3 class="box-title">Altre impostazioni</h3>
                    </div>
                    <div class="box-body">

                        <div class="form-group">
                            <label for="setFeed">SiteMap</label>
                            {!! Form::select('setSiteMap', ["No","Si"] ,$cList->get('sitemap') , ['class' => "form-control input-sm", 'id'=>"setSiteMap"]) !!}
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection

@push('style')
{{ Html::style('/node_modules/select2/dist/css/select2.min.css') }}
<Style>
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        color: #555;
    }
</Style>
@endpush

@push('scripts')
{{ Html::script('/node_modules/select2/dist/js/select2.min.js') }}
<script type="text/javascript">
    var $tagMulti = $('.tagsel').select2({tags: true});
    $tagMulti.val([{!! $cList->tags_reg !!}]).trigger('change');

    $('.multicat').each(function() {
        $(this).select2({categories: true}).val([{!! $cList->cats_reg !!}]).trigger('change');
    });


    $("#service").change(function(e) {
        e.preventDefault();
        $(".form-toggle").toggle();
    });
    $("#structure_id").change(function(e) {
        e.preventDefault();
        $('#model_id').empty();
        $.getJSON ("/admin/contentlist/listmodels/"+$('#structure_id').val(), function ( res ) {
        }).done(function(data) {
            $.each( data, function( key, val ) {
                $('#model_id').append('<option value=' + key + '>' + val + '</option>');
            });
        });
    });

    if ($("#setFeed").find('option:selected').val()==1) {
        $('.feed').show();
        setFeed();
    } else {
        $('.feed').hide();
    }

    $("#setFeed").change(function(e) {
        e.preventDefault();
        setFeed();
        $('.feed').toggle();
    });

    $("#feed_size, #feed_format, #feed_name").change(function() {
        if ($('#setFeed').find('option:selected').val()==1) setFeed();
    });

    function setFeed() {
        //$("#feed").val(null);
        if ($('#setFeed').find('option:selected').val()==1) {
            var ids = [];
            ids.push({feed_name:$('#feed_name').val()});
            ids.push({feed_size:$("#feed_size").find('option:selected').val()});
            ids.push({feed_format:$("#feed_format").find('option:selected').val()});
            $("#feed").val(JSON.stringify(ids));
        } else {
            $("#feed").val(null);
        }
    }

    $("#setSiteMap").change(function(e) {
        e.preventDefault();
        $("#sitemap").val($(this).find('option:selected').val());
    });
</script>
@endpush
