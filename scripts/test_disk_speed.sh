#!/bin/bash

echo "==================================="
echo "       Disk Speed Test"
echo "==================================="
echo ""

# Check if fio is installed
if ! command -v fio &> /dev/null; then
    echo "ℹ️  fio is not installed (recommended for accurate testing)"
    echo "   Install it with: sudo apt install fio"
    echo ""
    echo "📝 Falling back to dd method..."
    echo ""

    # DD-based tests
    TESTFILE="disk_speed_test.tmp"

    echo "⏳ Testing WRITE speed..."
    WRITE_RESULT=$(dd if=/dev/zero of=$TESTFILE bs=1M count=1024 oflag=direct 2>&1 | grep -oP '\d+\.?\d* [MG]B/s' | tail -1)
    echo "   Write Speed: $WRITE_RESULT"

    sync

    echo ""
    echo "⏳ Testing READ speed..."
    READ_RESULT=$(dd if=$TESTFILE of=/dev/null bs=1M iflag=direct 2>&1 | grep -oP '\d+\.?\d* [MG]B/s' | tail -1)
    echo "   Read Speed:  $READ_RESULT"

    rm -f $TESTFILE

else
    # FIO-based tests
    echo "✅ Using fio for accurate testing..."
    echo ""

    TESTFILE="fio_test_file"

    # Sequential Write Test
    echo "⏳ Testing Sequential WRITE..."
    fio --name=seqwrite --filename=$TESTFILE --rw=write --bs=1M --size=1G --numjobs=1 --runtime=10 --time_based --group_reporting --minimal > /tmp/fio_write.txt 2>/dev/null
    WRITE_KB=$(awk -F';' '{print $48}' /tmp/fio_write.txt)
    WRITE_IOPS=$(awk -F';' '{print $49}' /tmp/fio_write.txt)
    if [ -n "$WRITE_KB" ] && [ "$WRITE_KB" != "0" ]; then
        WRITE_MB=$(awk "BEGIN {printf \"%.2f\", $WRITE_KB / 1024}")
        WRITE_IOPS_INT=$(awk "BEGIN {printf \"%.0f\", $WRITE_IOPS}")
        echo "   Sequential Write: ${WRITE_MB} MB/s  |  ${WRITE_IOPS_INT} IOPS"
    else
        echo "   Sequential Write: Error parsing results"
    fi
    echo ""

    # Sequential Read Test
    echo "⏳ Testing Sequential READ..."
    fio --name=seqread --filename=$TESTFILE --rw=read --bs=1M --size=1G --numjobs=1 --runtime=10 --time_based --group_reporting --minimal > /tmp/fio_read.txt 2>/dev/null
    READ_KB=$(awk -F';' '{print $7}' /tmp/fio_read.txt)
    READ_IOPS=$(awk -F';' '{print $8}' /tmp/fio_read.txt)
    if [ -n "$READ_KB" ] && [ "$READ_KB" != "0" ]; then
        READ_MB=$(awk "BEGIN {printf \"%.2f\", $READ_KB / 1024}")
        READ_IOPS_INT=$(awk "BEGIN {printf \"%.0f\", $READ_IOPS}")
        echo "   Sequential Read:  ${READ_MB} MB/s  |  ${READ_IOPS_INT} IOPS"
    else
        echo "   Sequential Read: Error parsing results"
    fi
    echo ""

    # Random Write Test (4K blocks)
    echo "⏳ Testing Random WRITE (4K blocks)..."
    fio --name=randwrite --filename=$TESTFILE --rw=randwrite --bs=4K --size=1G --numjobs=1 --runtime=10 --time_based --group_reporting --minimal > /tmp/fio_randwrite.txt 2>/dev/null
    RANDWRITE_KB=$(awk -F';' '{print $48}' /tmp/fio_randwrite.txt)
    RANDWRITE_IOPS=$(awk -F';' '{print $49}' /tmp/fio_randwrite.txt)
    if [ -n "$RANDWRITE_IOPS" ] && [ "$RANDWRITE_IOPS" != "0" ]; then
        RANDWRITE_MB=$(awk "BEGIN {printf \"%.2f\", $RANDWRITE_KB / 1024}")
        RANDWRITE_IOPS_INT=$(awk "BEGIN {printf \"%.0f\", $RANDWRITE_IOPS}")
        echo "   Random Write:     ${RANDWRITE_MB} MB/s  |  ${RANDWRITE_IOPS_INT} IOPS"
    else
        echo "   Random Write: Error parsing results"
    fi
    echo ""

    # Random Read Test (4K blocks)
    echo "⏳ Testing Random READ (4K blocks)..."
    fio --name=randread --filename=$TESTFILE --rw=randread --bs=4K --size=1G --numjobs=1 --runtime=10 --time_based --group_reporting --minimal > /tmp/fio_randread.txt 2>/dev/null
    RANDREAD_KB=$(awk -F';' '{print $7}' /tmp/fio_randread.txt)
    RANDREAD_IOPS=$(awk -F';' '{print $8}' /tmp/fio_randread.txt)
    if [ -n "$RANDREAD_IOPS" ] && [ "$RANDREAD_IOPS" != "0" ]; then
        RANDREAD_MB=$(awk "BEGIN {printf \"%.2f\", $RANDREAD_KB / 1024}")
        RANDREAD_IOPS_INT=$(awk "BEGIN {printf \"%.0f\", $RANDREAD_IOPS}")
        echo "   Random Read:      ${RANDREAD_MB} MB/s  |  ${RANDREAD_IOPS_INT} IOPS"
    else
        echo "   Random Read: Error parsing results"
    fi
    echo ""

    # Cleanup
    rm -f $TESTFILE /tmp/fio_*.txt
fi

echo "==================================="
echo "        Test Complete!"
echo "==================================="

