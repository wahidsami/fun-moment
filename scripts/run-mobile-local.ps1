param(
  [string]$BaseApi = "http://10.0.2.2:8000/api/v1",
  [string]$GoogleMapsApiKey = ""
)

$ErrorActionPreference = "Stop"

flutter pub get

if ([string]::IsNullOrWhiteSpace($GoogleMapsApiKey)) {
  flutter run --dart-define=BASE_API=$BaseApi
} else {
  flutter run --dart-define=BASE_API=$BaseApi --dart-define=GOOGLE_MAPS_API_KEY=$GoogleMapsApiKey
}
