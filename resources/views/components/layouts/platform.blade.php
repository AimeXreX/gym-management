@props(['title' => null, 'breadcrumb' => null])
@include('layouts.platform', ['title' => $title, 'breadcrumb' => $breadcrumb, 'slot' => $slot])
