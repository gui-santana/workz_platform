import 'package:dio/dio.dart';

/// Cria e configura uma instância do Dio para comunicação com a API da Workz!.
class ApiClient {
  final Dio dio;

  ApiClient({required String baseUrl, required String token})
      : dio = Dio(BaseOptions(baseUrl: baseUrl)) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          // Adiciona o token de autorização em todas as requisições.
          options.headers['Authorization'] = 'Bearer $token';
          return handler.next(options);
        },
        onError: (DioException e, handler) {
          // Aqui podemos adicionar um tratamento de erro global,
          // como deslogar o usuário em caso de token inválido (401).
          print('Erro na requisição API: ${e.message}');
          return handler.next(e);
        },
      ),
    );
  }
}