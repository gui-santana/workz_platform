import 'package:flutter/material.dart';

enum ExpenseCategory {
  food,
  transport,
  entertainment,
  shopping,
  bills,
  health,
  education,
  travel,
  other,
}

extension ExpenseCategoryExtension on ExpenseCategory {
  String get displayName {
    switch (this) {
      case ExpenseCategory.food:
        return 'Food & Dining';
      case ExpenseCategory.transport:
        return 'Transport';
      case ExpenseCategory.entertainment:
        return 'Entertainment';
      case ExpenseCategory.shopping:
        return 'Shopping';
      case ExpenseCategory.bills:
        return 'Bills & Utilities';
      case ExpenseCategory.health:
        return 'Health & Fitness';
      case ExpenseCategory.education:
        return 'Education';
      case ExpenseCategory.travel:
        return 'Travel';
      case ExpenseCategory.other:
        return 'Other';
    }
  }
  
  IconData get icon {
    switch (this) {
      case ExpenseCategory.food:
        return Icons.restaurant;
      case ExpenseCategory.transport:
        return Icons.directions_car;
      case ExpenseCategory.entertainment:
        return Icons.movie;
      case ExpenseCategory.shopping:
        return Icons.shopping_bag;
      case ExpenseCategory.bills:
        return Icons.receipt_long;
      case ExpenseCategory.health:
        return Icons.local_hospital;
      case ExpenseCategory.education:
        return Icons.school;
      case ExpenseCategory.travel:
        return Icons.flight;
      case ExpenseCategory.other:
        return Icons.more_horiz;
    }
  }
  
  Color get color {
    switch (this) {
      case ExpenseCategory.food:
        return Colors.orange;
      case ExpenseCategory.transport:
        return Colors.blue;
      case ExpenseCategory.entertainment:
        return Colors.purple;
      case ExpenseCategory.shopping:
        return Colors.pink;
      case ExpenseCategory.bills:
        return Colors.red;
      case ExpenseCategory.health:
        return Colors.green;
      case ExpenseCategory.education:
        return Colors.indigo;
      case ExpenseCategory.travel:
        return Colors.teal;
      case ExpenseCategory.other:
        return Colors.grey;
    }
  }
}

class Expense {
  final String id;
  final String title;
  final double amount;
  final ExpenseCategory category;
  final DateTime date;
  final String? description;
  final String? receipt;
  final Map<String, dynamic>? metadata;

  Expense({
    required this.id,
    required this.title,
    required this.amount,
    required this.category,
    required this.date,
    this.description,
    this.receipt,
    this.metadata,
  });

  factory Expense.fromJson(Map<String, dynamic> json) {
    return Expense(
      id: json['id'],
      title: json['title'],
      amount: (json['amount'] as num).toDouble(),
      category: ExpenseCategory.values.firstWhere(
        (e) => e.toString() == json['category'],
        orElse: () => ExpenseCategory.other,
      ),
      date: DateTime.parse(json['date']),
      description: json['description'],
      receipt: json['receipt'],
      metadata: json['metadata']?.cast<String, dynamic>(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'amount': amount,
      'category': category.toString(),
      'date': date.toIso8601String(),
      'description': description,
      'receipt': receipt,
      'metadata': metadata,
    };
  }

  Expense copyWith({
    String? id,
    String? title,
    double? amount,
    ExpenseCategory? category,
    DateTime? date,
    String? description,
    String? receipt,
    Map<String, dynamic>? metadata,
  }) {
    return Expense(
      id: id ?? this.id,
      title: title ?? this.title,
      amount: amount ?? this.amount,
      category: category ?? this.category,
      date: date ?? this.date,
      description: description ?? this.description,
      receipt: receipt ?? this.receipt,
      metadata: metadata ?? this.metadata,
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Expense && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() {
    return 'Expense{id: $id, title: $title, amount: $amount, category: $category, date: $date}';
  }
}

class ExpenseSummary {
  final double totalAmount;
  final int totalCount;
  final Map<ExpenseCategory, double> categoryTotals;
  final Map<ExpenseCategory, int> categoryCounts;
  final DateTime startDate;
  final DateTime endDate;

  ExpenseSummary({
    required this.totalAmount,
    required this.totalCount,
    required this.categoryTotals,
    required this.categoryCounts,
    required this.startDate,
    required this.endDate,
  });

  factory ExpenseSummary.fromExpenses(List<Expense> expenses) {
    if (expenses.isEmpty) {
      return ExpenseSummary(
        totalAmount: 0,
        totalCount: 0,
        categoryTotals: {},
        categoryCounts: {},
        startDate: DateTime.now(),
        endDate: DateTime.now(),
      );
    }

    final categoryTotals = <ExpenseCategory, double>{};
    final categoryCounts = <ExpenseCategory, int>{};
    double totalAmount = 0;

    for (final expense in expenses) {
      totalAmount += expense.amount;
      categoryTotals[expense.category] = 
          (categoryTotals[expense.category] ?? 0) + expense.amount;
      categoryCounts[expense.category] = 
          (categoryCounts[expense.category] ?? 0) + 1;
    }

    expenses.sort((a, b) => a.date.compareTo(b.date));

    return ExpenseSummary(
      totalAmount: totalAmount,
      totalCount: expenses.length,
      categoryTotals: categoryTotals,
      categoryCounts: categoryCounts,
      startDate: expenses.first.date,
      endDate: expenses.last.date,
    );
  }

  ExpenseCategory? get topCategory {
    if (categoryTotals.isEmpty) return null;
    
    return categoryTotals.entries
        .reduce((a, b) => a.value > b.value ? a : b)
        .key;
  }

  double getCategoryPercentage(ExpenseCategory category) {
    if (totalAmount == 0) return 0;
    return ((categoryTotals[category] ?? 0) / totalAmount) * 100;
  }
}