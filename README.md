# PHP class for CDR api Grandstream UCM6202.
## Establishing connection and receive CDR data

Example use:

```
$client = new CdrApiClient();
$response = $client->sendChallengeAction();
if ($response !== '403') {
  $cdrList = $client->fetchCdrList();
  echo json_encode($cdrList);
}
```

## Instruction
[UCM_API_Guide.pdf](https://www.grandstream.com/hubfs/Product_Documentation/UCM_API_Guide.pdf)
