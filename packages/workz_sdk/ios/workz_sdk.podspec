Pod::Spec.new do |s|
  s.name             = 'workz_sdk'
  s.version          = '0.0.1'
  s.summary          = 'WorkzSDK for Flutter applications'
  s.description      = <<-DESC
The official SDK for building applications for the Workz! platform with Flutter.
                       DESC
  s.homepage         = 'https://workz.com'
  s.license          = { :file => '../LICENSE' }
  s.author           = { 'Workz' => 'dev@workz.com' }
  s.source           = { :path => '.' }
  s.source_files = 'Classes/**/*'
  s.dependency 'Flutter'
  s.platform = :ios, '11.0'

  # Flutter.framework does not contain a i386 slice.
  s.pod_target_xcconfig = { 'DEFINES_MODULE' => 'YES', 'EXCLUDED_ARCHS[sdk=iphonesimulator*]' => 'i386' }
  s.swift_version = '5.0'
end