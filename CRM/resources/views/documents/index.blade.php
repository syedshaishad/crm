@extends('layouts.app')
@section('title','Documents')
@section('content')
<x-page-header title="Documents" crumb="Files shared across clients, projects and your team" icon='<path d="M5 3h11l3 3v15H5z"/><path d="M15 3v6h4"/>'/>
@include('documents.explorer')
@endsection
