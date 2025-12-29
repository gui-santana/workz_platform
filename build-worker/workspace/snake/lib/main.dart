// main.dart
import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';

void main() => runApp(const SnakeGameApp());

class SnakeGameApp extends StatelessWidget {
  const SnakeGameApp({super.key});
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Snake em Flutter',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorSchemeSeed: Colors.green,
        brightness: Brightness.dark,
        useMaterial3: true,
      ),
      home: const GamePage(),
    );
  }
}

enum Direction { up, down, left, right }

class GamePage extends StatefulWidget {
  const GamePage({super.key});
  @override
  State<GamePage> createState() => _GamePageState();
}

class _GamePageState extends State<GamePage> {
  // Tamanho lógico do tabuleiro (responsivo em pixels, fixo em células)
  static const int rows = 20;
  static const int cols = 20;

  // Estado do jogo
  List<Point<int>> snake = [];
  late Point<int> food;
  Direction dir = Direction.right;
  Direction _nextDir = Direction.right;

  Timer? _timer;
  bool running = false;
  bool gameOver = false;

  int score = 0;
  int _tickMs = 220; // velocidade inicial (menor = mais rápido)
  final int _minTickMs = 80;

  final Random _rng = Random();

  @override
  void initState() {
    super.initState();
    _startGame();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startGame() {
    _timer?.cancel();
    snake = [
      Point(cols ~/ 2 - 1, rows ~/ 2),
      Point(cols ~/ 2, rows ~/ 2),
      Point(cols ~/ 2 + 1, rows ~/ 2),
    ];
    dir = Direction.right;
    _nextDir = Direction.right;
    score = 0;
    gameOver = false;
    _tickMs = 220;
    food = _spawnFood();
    running = true;
    _timer = Timer.periodic(Duration(milliseconds: _tickMs), (_) => _tick());
    setState(() {});
  }

  void _pauseResume() {
    if (gameOver) return;
    if (running) {
      _timer?.cancel();
      running = false;
    } else {
      running = true;
      _timer = Timer.periodic(Duration(milliseconds: _tickMs), (_) => _tick());
    }
    setState(() {});
  }

  void _restart() => _startGame();

  void _changeDirection(Direction newDir) {
    // evita reversão imediata
    if ((dir == Direction.up && newDir == Direction.down) ||
        (dir == Direction.down && newDir == Direction.up) ||
        (dir == Direction.left && newDir == Direction.right) ||
        (dir == Direction.right && newDir == Direction.left)) {
      return;
    }
    _nextDir = newDir;
  }

  void _tick() {
    if (!mounted || !running) return;
    dir = _nextDir;

    final head = snake.last;
    final delta = switch (dir) {
      Direction.up => const Point(0, -1),
      Direction.down => const Point(0, 1),
      Direction.left => const Point(-1, 0),
      Direction.right => const Point(1, 0),
    };
    final newHead = Point(head.x + delta.x, head.y + delta.y);

    // colisão com parede
    if (newHead.x < 0 || newHead.y < 0 || newHead.x >= cols || newHead.y >= rows) {
      _endGame();
      return;
    }
    // colisão com o próprio corpo
    if (snake.contains(newHead)) {
      _endGame();
      return;
    }

    // move
    snake.add(newHead);

    // comeu?
    if (newHead == food) {
      score += 1;
      food = _spawnFood();
      _maybeSpeedUp();
    } else {
      snake.removeAt(0); // não cresce
    }

    if (mounted) setState(() {});
  }

  void _endGame() {
    _timer?.cancel();
    running = false;
    gameOver = true;
    setState(() {});
  }

  void _maybeSpeedUp() {
    // acelera um pouco a cada 3 pontos (sem passar do mínimo)
    if (score % 3 == 0 && _tickMs > _minTickMs) {
      _tickMs = max(_minTickMs, _tickMs - 20);
      _timer?.cancel();
      _timer = Timer.periodic(Duration(milliseconds: _tickMs), (_) => _tick());
    }
  }

  Point<int> _spawnFood() {
    while (true) {
      final p = Point(_rng.nextInt(cols), _rng.nextInt(rows));
      if (!snake.contains(p)) return p;
    }
  }

  // Gestos: detecta swipe dominante
  void _onPanUpdate(DragUpdateDetails d) {
    if (!running || gameOver) return;
    final dx = d.delta.dx, dy = d.delta.dy;
    if (dx.abs() > dy.abs()) {
      _changeDirection(dx > 0 ? Direction.right : Direction.left);
    } else if (dy.abs() > dx.abs()) {
      _changeDirection(dy > 0 ? Direction.down : Direction.up);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            // Calcula célula para caber no menor lado, mantém quadrado
            final boardSize = min(constraints.maxWidth, constraints.maxHeight - 160);
            final cellSize = boardSize / max(rows, cols);

            return Column(
              children: [
                // Header / Placar
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    children: [
                      Text('SNKe', style: Theme.of(context).textTheme.titleLarge),
                      const Spacer(),
                      _Chip(text: 'Pontos: $score'),
                      const SizedBox(width: 8),
                      _Chip(text: running ? 'Vel: ${_tickMs}ms' : 'Pausado'),
                      const SizedBox(width: 8),
                      FilledButton.icon(
                        onPressed: _pauseResume,
                        icon: Icon(running ? Icons.pause : Icons.play_arrow),
                        label: Text(running ? 'Pausar' : 'Retomar'),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton.icon(
                        onPressed: _restart,
                        icon: const Icon(Icons.replay),
                        label: const Text('Reiniciar'),
                      ),
                    ],
                  ),
                ),

                // Tabuleiro
                Expanded(
                  child: Center(
                    child: GestureDetector(
                      onPanUpdate: _onPanUpdate,
                      child: Container(
                        width: boardSize,
                        height: boardSize,
                        decoration: BoxDecoration(
                          color: Colors.black.withOpacity(0.85),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: Colors.green.withOpacity(0.5), width: 2),
                          boxShadow: const [BoxShadow(blurRadius: 16, offset: Offset(0, 8))],
                        ),
                        child: CustomPaint(
                          painter: _BoardPainter(
                            rows: rows,
                            cols: cols,
                            cell: cellSize,
                            snake: snake,
                            food: food,
                            showGrid: true,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),

                // Controles de direção (útil em desktop)
                const SizedBox(height: 12),
                _DirectionPad(onTap: _changeDirection),
                const SizedBox(height: 12),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.green.withOpacity(0.15),
        border: Border.all(color: Colors.green.withOpacity(0.5)),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(text),
    );
  }
}

class _BoardPainter extends CustomPainter {
  _BoardPainter({
    required this.rows,
    required this.cols,
    required this.cell,
    required this.snake,
    required this.food,
    this.showGrid = false,
  });

  final int rows;
  final int cols;
  final double cell;
  final List<Point<int>> snake;
  final Point<int> food;
  final bool showGrid;
  final Paint _snakePaint = Paint()..color = const Color(0xFF4CAF50);
  final Paint _snakeHeadPaint = Paint()..color = const Color(0xFF8BC34A);
  final Paint _foodPaint = Paint()..color = const Color(0xFFF44336);
  final Paint _gridPaint = Paint()
    ..color = const Color(0x22FFFFFF)
    ..style = PaintingStyle.stroke
    ..strokeWidth = 0.5;

  @override
  void paint(Canvas canvas, Size size) {
    // Opcional: grade
    if (showGrid) {
      for (int r = 0; r <= rows; r++) {
        final y = r * cell;
        canvas.drawLine(Offset(0, y), Offset(cols * cell, y), _gridPaint);
      }
      for (int c = 0; c <= cols; c++) {
        final x = c * cell;
        canvas.drawLine(Offset(x, 0), Offset(x, rows * cell), _gridPaint);
      }
    }

    // Desenha comida
    _drawCell(canvas, food, _foodPaint, radius: cell * 0.28);

    // Desenha corpo
    for (int i = 0; i < snake.length - 1; i++) {
      _drawCell(canvas, snake[i], _snakePaint, radius: cell * 0.18);
    }

    // Cabeça destacada
    if (snake.isNotEmpty) {
      _drawCell(canvas, snake.last, _snakeHeadPaint, radius: cell * 0.22);
    }
  }

  void _drawCell(Canvas canvas, Point<int> p, Paint paint, {double? radius}) {
    final rect = Rect.fromLTWH(p.x * cell, p.y * cell, cell, cell);
    final r = Radius.circular(radius ?? cell * 0.12);
    final rrect = RRect.fromRectAndRadius(rect.deflate(cell * 0.12), r);
    canvas.drawRRect(rrect, paint);
  }

  @override
  bool shouldRepaint(covariant _BoardPainter old) {
    return snake != old.snake || food != old.food;
  }
}

class _DirectionPad extends StatelessWidget {
  const _DirectionPad({required this.onTap});
  final void Function(Direction) onTap;

  Widget _btn(IconData icon, Direction d) {
    return SizedBox(
      width: 64,
      height: 64,
      child: FilledButton.tonal(
        onPressed: () => onTap(d),
        child: Icon(icon, size: 28),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _btn(Icons.keyboard_arrow_up, Direction.up),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _btn(Icons.keyboard_arrow_left, Direction.left),
            const SizedBox(width: 6),
            _btn(Icons.keyboard_arrow_down, Direction.down),
            const SizedBox(width: 6),
            _btn(Icons.keyboard_arrow_right, Direction.right),
          ],
        ),
      ],
    );
  }
}