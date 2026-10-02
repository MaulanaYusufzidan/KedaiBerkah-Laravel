@extends('layouts.admin')

@section('title', 'Tambah ke Menu')

@section('content')
    <x-admin.page-header title="Tambah ke menu" />
    @include('admin.daily-menus._form', ['menu' => null, 'action' => route('admin.daily-menus.store')])
@endsection
