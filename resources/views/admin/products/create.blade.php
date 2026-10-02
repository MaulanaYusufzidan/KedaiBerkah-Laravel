@extends('layouts.admin')

@section('title', 'Tambah Produk')

@section('content')
    <x-admin.page-header title="Tambah produk" />
    @include('admin.products._form', ['product' => null, 'action' => route('admin.products.store')])
@endsection
