import 'dart:convert';
import 'package:workz_sdk/workz_sdk.dart';
import '../models/expense.dart';

class ExpenseService {
  static final ExpenseService _instance = ExpenseService._internal();
  static ExpenseService get instance => _instance;
  
  ExpenseService._internal();

  List<Expense> _expenses = [];
  bool _isInitialized = false;

  List<Expense> get expenses => List.unmodifiable(_expenses);
  bool get isInitialized => _isInitialized;

  Future<void> initialize() async {
    if (_isInitialized) return;
    
    try {
      await _loadExpenses();
      _isInitialized = true;
      print('ExpenseService initialized with ${_expenses.length} expenses');
    } catch (error) {
      print('Failed to initialize ExpenseService: $error');
      rethrow;
    }
  }

  Future<void> _loadExpenses() async {
    try {
      final expensesJson = await WorkzSDK.kv.get('expenses');
      if (expensesJson != null) {
        final List<dynamic> expensesList = json.decode(expensesJson);
        _expenses = expensesList
            .map((json) => Expense.fromJson(json))
            .toList();
        
        // Sort by date (newest first)
        _expenses.sort((a, b) => b.date.compareTo(a.date));
      }
    } catch (error) {
      print('Failed to load expenses: $error');
      _expenses = [];
    }
  }

  Future<void> _saveExpenses() async {
    try {
      final expensesJson = json.encode(_expenses.map((e) => e.toJson()).toList());
      await WorkzSDK.kv.set('expenses', expensesJson);
    } catch (error) {
      print('Failed to save expenses: $error');
      rethrow;
    }
  }

  Future<void> addExpense(Expense expense) async {
    _expenses.add(expense);
    _expenses.sort((a, b) => b.date.compareTo(a.date));
    await _saveExpenses();
  }

  Future<void> updateExpense(Expense expense) async {
    final index = _expenses.indexWhere((e) => e.id == expense.id);
    if (index != -1) {
      _expenses[index] = expense;
      _expenses.sort((a, b) => b.date.compareTo(a.date));
      await _saveExpenses();
    }
  }

  Future<void> deleteExpense(String expenseId) async {
    _expenses.removeWhere((e) => e.id == expenseId);
    await _saveExpenses();
  }

  Expense? getExpenseById(String id) {
    try {
      return _expenses.firstWhere((e) => e.id == id);
    } catch (e) {
      return null;
    }
  }

  List<Expense> getExpensesByDateRange(DateTime start, DateTime end) {
    return _expenses.where((expense) {
      return expense.date.isAfter(start.subtract(Duration(days: 1))) &&
             expense.date.isBefore(end.add(Duration(days: 1)));
    }).toList();
  }

  List<Expense> getExpensesByCategory(ExpenseCategory category) {
    return _expenses.where((expense) => expense.category == category).toList();
  }

  List<Expense> getExpensesThisMonth() {
    final now = DateTime.now();
    final startOfMonth = DateTime(now.year, now.month, 1);
    final endOfMonth = DateTime(now.year, now.month + 1, 0);
    return getExpensesByDateRange(startOfMonth, endOfMonth);
  }

  List<Expense> getExpensesThisWeek() {
    final now = DateTime.now();
    final startOfWeek = now.subtract(Duration(days: now.weekday - 1));
    final endOfWeek = startOfWeek.add(Duration(days: 6));
    return getExpensesByDateRange(startOfWeek, endOfWeek);
  }

  List<Expense> getExpensesToday() {
    final now = DateTime.now();
    final startOfDay = DateTime(now.year, now.month, now.day);
    final endOfDay = startOfDay.add(Duration(days: 1));
    return getExpensesByDateRange(startOfDay, endOfDay);
  }

  ExpenseSummary getSummary({DateTime? startDate, DateTime? endDate}) {
    List<Expense> filteredExpenses = _expenses;
    
    if (startDate != null && endDate != null) {
      filteredExpenses = getExpensesByDateRange(startDate, endDate);
    }
    
    return ExpenseSummary.fromExpenses(filteredExpenses);
  }

  ExpenseSummary getMonthlySummary() {
    return ExpenseSummary.fromExpenses(getExpensesThisMonth());
  }

  ExpenseSummary getWeeklySummary() {
    return ExpenseSummary.fromExpenses(getExpensesThisWeek());
  }

  ExpenseSummary getDailySummary() {
    return ExpenseSummary.fromExpenses(getExpensesToday());
  }

  Map<String, double> getMonthlyTrends(int months) {
    final trends = <String, double>{};
    final now = DateTime.now();
    
    for (int i = 0; i < months; i++) {
      final month = DateTime(now.year, now.month - i, 1);
      final nextMonth = DateTime(now.year, now.month - i + 1, 1);
      final monthExpenses = getExpensesByDateRange(month, nextMonth);
      
      final monthKey = '${month.year}-${month.month.toString().padLeft(2, '0')}';
      trends[monthKey] = monthExpenses.fold(0.0, (sum, expense) => sum + expense.amount);
    }
    
    return trends;
  }

  Map<ExpenseCategory, List<Expense>> getExpensesByCategories() {
    final categorizedExpenses = <ExpenseCategory, List<Expense>>{};
    
    for (final expense in _expenses) {
      categorizedExpenses.putIfAbsent(expense.category, () => []).add(expense);
    }
    
    return categorizedExpenses;
  }

  Future<void> importExpenses(List<Expense> expenses) async {
    for (final expense in expenses) {
      // Check if expense already exists
      if (!_expenses.any((e) => e.id == expense.id)) {
        _expenses.add(expense);
      }
    }
    
    _expenses.sort((a, b) => b.date.compareTo(a.date));
    await _saveExpenses();
  }

  Future<List<Expense>> exportExpenses({DateTime? startDate, DateTime? endDate}) async {
    if (startDate != null && endDate != null) {
      return getExpensesByDateRange(startDate, endDate);
    }
    return List.from(_expenses);
  }

  Future<void> clearAllExpenses() async {
    _expenses.clear();
    await _saveExpenses();
  }

  // Search functionality
  List<Expense> searchExpenses(String query) {
    if (query.isEmpty) return _expenses;
    
    final lowercaseQuery = query.toLowerCase();
    return _expenses.where((expense) {
      return expense.title.toLowerCase().contains(lowercaseQuery) ||
             expense.description?.toLowerCase().contains(lowercaseQuery) == true ||
             expense.category.displayName.toLowerCase().contains(lowercaseQuery);
    }).toList();
  }

  // Statistics
  double getTotalSpent({DateTime? startDate, DateTime? endDate}) {
    List<Expense> filteredExpenses = _expenses;
    
    if (startDate != null && endDate != null) {
      filteredExpenses = getExpensesByDateRange(startDate, endDate);
    }
    
    return filteredExpenses.fold(0.0, (sum, expense) => sum + expense.amount);
  }

  double getAverageExpense({DateTime? startDate, DateTime? endDate}) {
    List<Expense> filteredExpenses = _expenses;
    
    if (startDate != null && endDate != null) {
      filteredExpenses = getExpensesByDateRange(startDate, endDate);
    }
    
    if (filteredExpenses.isEmpty) return 0.0;
    
    final total = filteredExpenses.fold(0.0, (sum, expense) => sum + expense.amount);
    return total / filteredExpenses.length;
  }

  ExpenseCategory? getMostExpensiveCategory({DateTime? startDate, DateTime? endDate}) {
    List<Expense> filteredExpenses = _expenses;
    
    if (startDate != null && endDate != null) {
      filteredExpenses = getExpensesByDateRange(startDate, endDate);
    }
    
    if (filteredExpenses.isEmpty) return null;
    
    final categoryTotals = <ExpenseCategory, double>{};
    
    for (final expense in filteredExpenses) {
      categoryTotals[expense.category] = 
          (categoryTotals[expense.category] ?? 0) + expense.amount;
    }
    
    return categoryTotals.entries
        .reduce((a, b) => a.value > b.value ? a : b)
        .key;
  }

  // Budget tracking (future feature)
  Future<void> setBudget(ExpenseCategory category, double amount) async {
    try {
      final budgets = await _loadBudgets();
      budgets[category.toString()] = amount;
      await _saveBudgets(budgets);
    } catch (error) {
      print('Failed to set budget: $error');
    }
  }

  Future<double?> getBudget(ExpenseCategory category) async {
    try {
      final budgets = await _loadBudgets();
      return budgets[category.toString()];
    } catch (error) {
      print('Failed to get budget: $error');
      return null;
    }
  }

  Future<Map<String, double>> _loadBudgets() async {
    try {
      final budgetsJson = await WorkzSDK.kv.get('budgets');
      if (budgetsJson != null) {
        final Map<String, dynamic> budgetsMap = json.decode(budgetsJson);
        return budgetsMap.map((key, value) => MapEntry(key, (value as num).toDouble()));
      }
      return {};
    } catch (error) {
      print('Failed to load budgets: $error');
      return {};
    }
  }

  Future<void> _saveBudgets(Map<String, double> budgets) async {
    try {
      final budgetsJson = json.encode(budgets);
      await WorkzSDK.kv.set('budgets', budgetsJson);
    } catch (error) {
      print('Failed to save budgets: $error');
    }
  }
}