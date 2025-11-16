@extends('errors.layout')

@section('title', __('Something went wrong'))
@section('status', '500')
@section('message', __('An unexpected error occurred. Please try again later.'))
