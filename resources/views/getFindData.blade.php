<div class="panel panel-default col-md-12 col-sm-12 " id='showFindData'>
    @if ($foundProtocolsCount > 0)
        <div class="row bg-info">
            <div class="form-control-static  text-center">Η αναζήτηση επέστρεψε
                <strong>{{ $foundProtocolsCount }}</strong>
                {{ $foundProtocolsCount == 1 ? 'αποτέλεσμα' : ' αποτελέσματα' }}.
            </div>
        </div>

        @if ($protocols->links())
            <div class="row">
                <div class="col-md-12 col-sm-12 small text-center">
                    <span class="small">{{ $protocols->links() }}</span>
                </div>
            </div>
        @endif

        <div class="row bg-primary">
            <div class="form-control-static col-md-1 col-sm-1 text-center ">Αρ.Πρωτ.</div>
            <div class="form-control-static col-md-1 col-sm-1  text-center ">Ημ.Πρωτ.</div>
            <div class="form-control-static col-md-5 col-sm-5  ">{{ $fields[$searchField1] ?? '' }}</div>
            <div class="form-control-static col-md-3 col-sm-3  ">{{ $fields[$searchField2] ?? '' }}</div>
            <div class="form-control-static col-md-2 col-sm-2  ">{{ $fields[$searchField3] ?? '' }}</div>
        </div>

        @php($i = 0)
        @foreach ($protocols as $protocol)
            @if ($i % 2)
                <div class="row line" onclick="window.location.href='{{ URL::to('/') }}/home/{{ $protocol->id }}'">
                @else
                    <div class="row line line2"
                        onclick="window.location.href='{{ URL::to('/') }}/home/{{ $protocol->id }}'">
            @endif
            <div class="col-md-1 col-sm-1 text-center ">
                <a href="{{ URL::to('/') }}/home/{{ $protocol->id }}/1" class="" role="button"
                    title="Αντιγραφή ως Νέο"> <img src="{{ URL::to('/') }}/images/copy-stamp.png" height="20" />
                </a>
                {{ $protocol->protocolnum }}
            </div>
            <div class="col-md-1 col-sm-1 text-center ">{{ $protocol->protocoldate }}</div>
            {{-- R1a: values and the search term are escaped first, then the term is marked (they were printed raw) --}}
            @foreach ([[$searchField1, $searchData1, 4], [$searchField2, $searchData2, 3], [$searchField3, $searchData3, 3]] as [$sField, $sData, $sWidth])
                <div class="col-md-{{ $sWidth }} col-sm-{{ $sWidth }} ">
                    @if (array_key_exists($sField, $attachmentfields))
                        <ul class='list-inline'>
                            @foreach ($protocol->attachments as $attachment)
                                <li>{!! \App\Support\Highlight::html($attachment->$sField, $sData) !!}</li>
                            @endforeach
                        </ul>
                    @elseif ($sField === 'diekperaiosi')
                        {!! \App\Support\Highlight::html($protocol->assignee_names, $sData) !!}
                    @else
                        {!! \App\Support\Highlight::html($protocol->$sField, $sData) !!}
                    @endif
                </div>
            @endforeach
</div>
@php($i++)
@endforeach
@else
<div class="row bg-info">
    <div class="form-control-static  text-center">Δεν βρέθηκαν αποτελέσματα. Περιορίστε τα κριτήρια αναζήτησης.</div>
</div>
@endif
</div>

<script>
    $('.pagination a').on('click', function(e) {
        e.preventDefault();
        let page = $(this).attr('href').split('page=')[1];
        getFindData(page)
    });
</script>

<style>
    .line {
        padding-top: 7px;
        padding-bottom: 5px;
        cursor: pointer;
    }

    .line:hover {
        background-color: #E6E6E6
    }

    .line2 {
        background-color: WhiteSmoke
    }
</style>
