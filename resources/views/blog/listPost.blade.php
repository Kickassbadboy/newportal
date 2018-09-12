@extends('layouts.admin')

@section('breadcrumb')
{!! $breadcrumb->add('Lista Post')->render() !!}
@stop

@section('content')

    <!-- Main content -->
    @include('ui.messages')
    <div class="row">
        <div class="col-xs-12">
            <div class="box" style="padding-top: 20px;">
                {!!
                    $list->columns(['id','name'=>'nome','created_at'=>'Creato il','updated_at'=>'Aggiornato il',"comment"=>'Commenti'])
                    ->actions([url('/admin/comments')=>'Lista commenti',url('/admin/comments/create')=>'Nuovo commento'])
                    ->customizes('updated_at',function($row){
                        return Carbon\Carbon::parse($row['updated_at'])->format('d/m/Y');
                    })
                    ->customizes('created_at',function($row){
                        return Carbon\Carbon::parse($row['created_at'])->format('d/m/Y');
                    })
                    ->customizes('comment',function($row){
                        return count($row->comments);
                    })
                    ->render()
                !!}
            </div> <!-- /.box -->
        </div> <!-- /.col -->
    </div> <!-- /.row -->
    <!-- /.content -->
@stop