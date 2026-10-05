@extends('layouts.admin')

@section('title', 'Tambah Area Pengiriman')

@section('content')
    <x-admin.page-header title="Tambah area pengiriman" />
    @include('admin.delivery-areas._form', ['area' => null, 'action' => route('admin.delivery-areas.store')])
@endsection
