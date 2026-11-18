<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Εκτύπωση email </title>

    @php
        use ZBateson\MailMimeParser\Header\HeaderConsts;
        // R5b review: the bodies as the inbox reads them (a winmail.dat's text/HTML when the e-mail has none)
        $bodyHtml = (string) \App\Support\Mail\MailContent::html($mailMessage);
        $bodyText = (string) ($bodyText ?? $mailMessage->getTextContent());
    @endphp


    <!-- Styles -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
        integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <div class="row">&nbsp;</div>
        <div class="panel panel-default col-md-12 col-sm-12  ">
            <div class="row bg-warning">
                <div class="form-control-static col-md-1 col-sm-1  "><strong>Από:</strong></div>
                <div class="form-control-static col-md-8 col-sm-8  ">
                    {{ $mailMessage->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getName() }}
                    {{ $mailMessage->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getName()? '<' .$mailMessage->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getEmail() .'>': $mailMessage->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getEmail() }}
                </div>
                <div class="form-control-static col-md-1 col-sm-1 "><strong>Ημνία:</strong></div>
                <div class="form-control-static col-md-2 col-sm-2 ">
                    {{ \Carbon\Carbon::parse($mailMessage->getHeader(HeaderConsts::DATE)->getDateTime())->timezone(App\Models\Config::getConfigValueOf('timeZone'))->format('d/m/Y H:i:s') }}
                </div>
            </div>
            <div class="row bg-warning ">
                <div class="form-control-static col-md-1 col-sm-1"><strong>Θέμα:</strong></div>
                @php
                    $subject = $mailMessage->getHeaderValue(HeaderConsts::SUBJECT);
                @endphp
                <div class="form-control-static col-md-11 col-sm-11  "><strong>{{ $subject }}</strong></div>
            </div>
            @if ($mailMessage->getHeader(HeaderConsts::TO) && $mailMessage->getHeader(HeaderConsts::TO)->getRawValue())
                <div class="row bg-warning ">
                    <div class="form-control-static col-md-1 col-sm-1"><strong>Προς:</strong></div>
                    <div class="form-control-static col-md-11 col-sm-11">
                        @foreach ($mailMessage->getHeader(HeaderConsts::TO)->getAddresses() as $getTo)
                            {{ $getTo->getName() ?? null }}{{ $getTo->getName() ? '<' . $getTo->getEmail() . '>' : $getTo->getEmail() }}
                            @if (!$loop->last)
                                ,&nbsp;
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($mailMessage->getHeader(HeaderConsts::CC) && $mailMessage->getHeader(HeaderConsts::CC)->getRawValue())
                <div class="row bg-warning ">
                    <div class="form-control-static col-md-1 col-sm-1"><strong>Κοιν:</strong></div>
                    <div class="form-control-static col-md-11 col-sm-11">
                        @foreach ($mailMessage->getHeader(HeaderConsts::CC)->getAddresses() as $getCc)
                            {{ $getCc->getName() ?? null }}{{ $getCc->getName() ? '<' . $getCc->getEmail() . '>' : $getCc->getEmail() }}
                            @if (!$loop->last)
                                ,&nbsp;
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($mailMessage->getHeader(HeaderConsts::REPLY_TO) && $mailMessage->getHeader(HeaderConsts::REPLY_TO)->getRawValue())
                <div class="row bg-warning ">
                    <div class="form-control-static col-md-1 col-sm-1"><strong>Απάντηση:</strong></div>
                    <div class="form-control-static col-md-11 col-sm-11">
                        @foreach ($mailMessage->getHeader(HeaderConsts::REPLY_TO)->getAddresses() as $getReplyTo)
                            {{ $getReplyTo->getName() ?? null }}{{ $getReplyTo->getName() ? '<' . $getReplyTo->getEmail() . '>' : $getReplyTo->getEmail() }}
                            @if (!$loop->last)
                                ,&nbsp;
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            <hr>
            @if (strlen($bodyHtml))
                <div class="row">
                    <div class="form-control-static col-md-12 col-sm-12  ">{!! str_replace('iso-8859-7', 'utf-8', $bodyHtml) !!}</div>
                </div>
            @endif
            @if (strlen($bodyHtml) && strlen($bodyText))
                <hr>
            @endif
            @if (strlen($bodyText))
                <div class="row">
                    <div class="form-control-static col-md-12 col-sm-12  small"
                        style="white-space: pre-wrap; overflow: hidden">{{ $bodyText }}</div>
                </div>
            @endif
            <hr>
            @if ($mailMessage->getAttachmentCount())
                <div class="row bg-warning">
                    <div class="form-control-static col-md-2 col-sm-2"><strong>Συνημμένα:</strong></div>
                    <div class="form-control-static col-md-10 col-sm-10 ">
                        @foreach ($mailMessage->getAllAttachmentParts() as $key => $attachment)
                            @php
                                $filename = $attachment->getFilename();
                            @endphp
                            {{ $filename }}
                            @if (!$loop->last)
                                , &nbsp;
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>

</html>
