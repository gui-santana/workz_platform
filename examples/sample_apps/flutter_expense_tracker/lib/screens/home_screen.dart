import 'package:flutter/material.dart';
import '../models/expense.dart';
import '../services/expense_service.dart';
import '../widgets/expense_card.dart';
import '../widgets/summary_card.dart';
import '../widgets/category_chart.dart';
import 'add_expense_screen.dart';
import 'expense_detail_screen.dart';
import 'statistics_screen.dart';

class HomeScreen extends StatefulWidget {
  @override
  _HomeScreenState createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with TickerProviderStateMixin {
  final ExpenseService _expenseService = ExpenseService.instance;
  late TabController _tabController;
  
  String _searchQuery = '';
  ExpenseCategory? _selectedCategory;
  
  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  List<Expense> get _filteredExpenses {
    List<Expense> expenses = _expenseService.expenses;
    
    // Apply search filter
    if (_searchQuery.isNotEmpty) {
      expenses = _expenseService.searchExpenses(_searchQuery);
    }
    
    // Apply category filter
    if (_selectedCategory != null) {
      expenses = expenses.where((e) => e.category == _selectedCategory).toList();
    }
    
    return expenses;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Expense Tracker'),
        actions: [
          IconButton(
            icon: Icon(Icons.analytics),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (context) => StatisticsScreen()),
              );
            },
          ),
          PopupMenuButton<String>(
            onSelected: _handleMenuAction,
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'export',
                child: Row(
                  children: [
                    Icon(Icons.download),
                    SizedBox(width: 8),
                    Text('Export Data'),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'clear',
                child: Row(
                  children: [
                    Icon(Icons.clear_all, color: Colors.red),
                    SizedBox(width: 8),
                    Text('Clear All', style: TextStyle(color: Colors.red)),
                  ],
                ),
              ),
            ],
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: 'Today'),
            Tab(text: 'This Week'),
            Tab(text: 'This Month'),
          ],
        ),
      ),
      body: Column(
        children: [
          // Search and filter bar
          _buildSearchAndFilter(),
          
          // Tab content
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _buildExpenseList(_expenseService.getExpensesToday()),
                _buildExpenseList(_expenseService.getExpensesThisWeek()),
                _buildExpenseList(_expenseService.getExpensesThisMonth()),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addExpense,
        child: Icon(Icons.add),
        tooltip: 'Add Expense',
      ),
    );
  }

  Widget _buildSearchAndFilter() {
    return Container(
      padding: EdgeInsets.all(16),
      child: Column(
        children: [
          // Search bar
          TextField(
            decoration: InputDecoration(
              hintText: 'Search expenses...',
              prefixIcon: Icon(Icons.search),
              suffixIcon: _searchQuery.isNotEmpty
                  ? IconButton(
                      icon: Icon(Icons.clear),
                      onPressed: () {
                        setState(() {
                          _searchQuery = '';
                        });
                      },
                    )
                  : null,
            ),
            onChanged: (value) {
              setState(() {
                _searchQuery = value;
              });
            },
          ),
          
          SizedBox(height: 12),
          
          // Category filter
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                FilterChip(
                  label: Text('All'),
                  selected: _selectedCategory == null,
                  onSelected: (selected) {
                    setState(() {
                      _selectedCategory = null;
                    });
                  },
                ),
                SizedBox(width: 8),
                ...ExpenseCategory.values.map((category) {
                  return Padding(
                    padding: EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(category.displayName),
                      selected: _selectedCategory == category,
                      onSelected: (selected) {
                        setState(() {
                          _selectedCategory = selected ? category : null;
                        });
                      },
                      avatar: Icon(
                        category.icon,
                        size: 16,
                        color: _selectedCategory == category 
                            ? Colors.white 
                            : category.color,
                      ),
                    ),
                  );
                }).toList(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExpenseList(List<Expense> periodExpenses) {
    // Apply filters to period expenses
    List<Expense> displayExpenses = periodExpenses;
    
    if (_searchQuery.isNotEmpty) {
      displayExpenses = periodExpenses.where((expense) {
        final query = _searchQuery.toLowerCase();
        return expense.title.toLowerCase().contains(query) ||
               expense.description?.toLowerCase().contains(query) == true ||
               expense.category.displayName.toLowerCase().contains(query);
      }).toList();
    }
    
    if (_selectedCategory != null) {
      displayExpenses = displayExpenses
          .where((expense) => expense.category == _selectedCategory)
          .toList();
    }

    return RefreshIndicator(
      onRefresh: () async {
        setState(() {});
      },
      child: CustomScrollView(
        slivers: [
          // Summary card
          SliverToBoxAdapter(
            child: SummaryCard(
              expenses: displayExpenses,
              title: _getPeriodTitle(),
            ),
          ),
          
          // Category chart (if there are expenses)
          if (displayExpenses.isNotEmpty)
            SliverToBoxAdapter(
              child: CategoryChart(expenses: displayExpenses),
            ),
          
          // Expenses list
          if (displayExpenses.isEmpty)
            SliverFillRemaining(
              child: _buildEmptyState(),
            )
          else
            SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final expense = displayExpenses[index];
                  return ExpenseCard(
                    expense: expense,
                    onTap: () => _viewExpenseDetail(expense),
                    onEdit: () => _editExpense(expense),
                    onDelete: () => _deleteExpense(expense),
                  );
                },
                childCount: displayExpenses.length,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    String message;
    IconData icon;
    
    if (_searchQuery.isNotEmpty || _selectedCategory != null) {
      message = 'No expenses match your filters';
      icon = Icons.search_off;
    } else {
      message = 'No expenses yet.\nTap + to add your first expense!';
      icon = Icons.receipt_long;
    }
    
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            icon,
            size: 80,
            color: Colors.grey[400],
          ),
          SizedBox(height: 16),
          Text(
            message,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 18,
              color: Colors.grey[600],
            ),
          ),
          if (_searchQuery.isEmpty && _selectedCategory == null) ...[
            SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _addExpense,
              icon: Icon(Icons.add),
              label: Text('Add Expense'),
            ),
          ],
        ],
      ),
    );
  }

  String _getPeriodTitle() {
    switch (_tabController.index) {
      case 0:
        return 'Today';
      case 1:
        return 'This Week';
      case 2:
        return 'This Month';
      default:
        return 'Expenses';
    }
  }

  void _addExpense() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => AddExpenseScreen()),
    );
    
    if (result == true) {
      setState(() {});
    }
  }

  void _viewExpenseDetail(Expense expense) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ExpenseDetailScreen(expense: expense),
      ),
    );
  }

  void _editExpense(Expense expense) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => AddExpenseScreen(expense: expense),
      ),
    );
    
    if (result == true) {
      setState(() {});
    }
  }

  void _deleteExpense(Expense expense) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete Expense'),
        content: Text('Are you sure you want to delete "${expense.title}"?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: Text('Delete'),
          ),
        ],
      ),
    );
    
    if (confirmed == true) {
      try {
        await _expenseService.deleteExpense(expense.id);
        setState(() {});
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Expense deleted'),
            action: SnackBarAction(
              label: 'Undo',
              onPressed: () async {
                await _expenseService.addExpense(expense);
                setState(() {});
              },
            ),
          ),
        );
      } catch (error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to delete expense: $error'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _handleMenuAction(String action) async {
    switch (action) {
      case 'export':
        await _exportData();
        break;
      case 'clear':
        await _clearAllData();
        break;
    }
  }

  Future<void> _exportData() async {
    try {
      final expenses = await _expenseService.exportExpenses();
      
      // In a real app, you would implement actual export functionality
      // For now, just show a message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Exported ${expenses.length} expenses'),
        ),
      );
    } catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Export failed: $error'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _clearAllData() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Clear All Data'),
        content: Text(
          'This will permanently delete all your expenses. This action cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: Text('Clear All'),
          ),
        ],
      ),
    );
    
    if (confirmed == true) {
      try {
        await _expenseService.clearAllExpenses();
        setState(() {});
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('All expenses cleared')),
        );
      } catch (error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to clear expenses: $error'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}