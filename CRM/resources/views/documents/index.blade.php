@extends('layouts.app')
@section('title','Documents')
@section('content')
<div class="page-head"><h1>Documents</h1><div class="crumb">Files shared across clients, projects and your team.</div></div>
@include('documents.explorer')
@endsection
