<?php

namespace Hawk\HawkiClientBackend\Value;

enum ClientConfigType: string
{
    case CONNECTED = 'connected';
    case CONNECTION_REQUEST = 'connect_request';
}
