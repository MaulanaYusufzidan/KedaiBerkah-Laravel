@extends('layouts.admin')

@section('title', 'Edit Menu')

@section('content')
    <x-admin.page-header title="Edit menu" />
    @include('admin.daily-menus._form', [
        'menu' => $menu,
        'action' => route('admin.daily-menus.update', $menu),
        'products' => collect(),
    ])
@endsection
