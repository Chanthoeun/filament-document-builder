<div class="header-block mb-4 text-center">
    @if(!empty($block['logo']))
        <img src="{{ Storage::url($block['logo']) }}" alt="Logo" style="max-height: 100px; margin-bottom: 10px;">
    @endif
    
    @if(!empty($block['title']))
        <h1 class="text-2xl font-bold">{{ $block['title'] }}</h1>
    @endif
    
    @if(!empty($block['subtitle']))
        <h3 class="text-xl">{{ $block['subtitle'] }}</h3>
    @endif
</div>
